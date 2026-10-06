<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/lib/common.php';
require_once dirname(__DIR__).'/lib/customs.php';
tp_admin_required();

$mrn=strtoupper(trim((string)($_GET['mrn']??$_POST['mrn']??'')));
$ship=trim((string)($_GET['ship']??$_POST['ship']??''));
$arrival=trim((string)($_GET['arrival']??$_POST['arrival']??''));
$departure=trim((string)($_GET['departure']??$_POST['departure']??''));
$result=null;$error='';$mode='';$bridgeNote='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $mode=(string)($_POST['mode']??'');
    try{
        if($mode==='cargo'){
            $cargoNo=strtoupper(trim((string)($_POST['cargo_no']??'')));
            if($mrn!=='' && !str_starts_with($cargoNo,$mrn)){ throw new RuntimeException('입력한 cargMtNo가 현재 MRN으로 시작하지 않습니다. 다른 입항건의 화물번호일 수 있어 조회를 중단했습니다.'); }
            $result=tp_customs_lookup_cargo($cargoNo);
        }elseif($mode==='mbl' || $mode==='hbl'){
            $result=tp_customs_lookup_bl($mode,(string)($_POST['bl_no']??''),(string)($_POST['bl_year']??''));
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
function bp(string $s): string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function render_row(array $r): void{
    $pn=(string)($r['product_name']??''); $tesla=!empty($r['tesla_match']);
    echo '<div class="card shadow-sm mt-3"><div class="card-body">';
    echo '<div class="d-flex justify-content-between flex-wrap gap-2"><b class="fs-5">API001 조회결과</b>'.($tesla?'<span class="badge text-bg-danger">TESLA 품명 일치</span>':'<span class="badge text-bg-secondary">TESLA 품명 미확인</span>').'</div>';
    echo '<div class="row g-3 mt-1 small">';
    $items=['cargo_no'=>'cargMtNo','mbl_no'=>'MBL','hbl_no'=>'HBL','product_name'=>'품명(prnm)','ship_name'=>'선박명','loading_port'=>'적재항','discharge_port'=>'양륙항','entry_date'=>'입항일자','package_count'=>'포장개수','total_weight'=>'총중량','weight_unit'=>'중량단위','progress_status'=>'진행상태'];
    foreach($items as $k=>$label){$v=(string)($r[$k]??''); echo '<div class="col-md-4"><b>'.bp($label).'</b><br>'.bp($v!==''?$v:'-').'</div>';}
    echo '</div></div></div>';
}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MRN 화물연결 실험실</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f6f8}.card{border:0;border-radius:16px}code{word-break:break-all}.step{font-weight:700}</style></head><body><div class="container py-4" style="max-width:1200px">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h2 class="fw-bold mb-1">MRN → 화물연결 실험실</h2><div class="text-secondary">v1.8.6.1 · MRN 직접입력 + API001 MSN 제한 검증</div></div><div><a class="btn btn-outline-dark" href="shanghai_history.php">Shanghai 이력</a> <a class="btn btn-outline-dark" href="cargo_link.php">화물연결 진단</a> <a class="btn btn-outline-primary" href="bridge_status.php">공식 연결경로 점검</a></div></div>
<div class="alert alert-info mt-4"><b>공식 API001 입력:</b> 화물관리번호(cargMtNo, 15~19자리) 또는 MBL/HBL + 입항년도입니다. <b>11자리 MRN은 API001 입력값이 아닙니다.</b> 따라서 이 화면은 MRN 뒤에 번호를 임의로 붙이지 않습니다.</div>
<div class="card shadow-sm mb-3"><div class="card-body"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><b class="fs-5">MRN 직접 입력</b><div class="small text-secondary">Shanghai 이력에 없는 선박도 바로 MSN 검증 화면으로 보낼 수 있습니다.</div></div><button type="button" class="btn btn-outline-primary btn-sm" onclick="setGlovisChorus()">GLOVIS CHORUS 테스트값 넣기</button></div><form method="get" action="msn_scan.php"><div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label fw-bold">MRN 11자리</label><input class="form-control" name="mrn" value="<?=bp($mrn)?>" maxlength="11" pattern="[A-Za-z0-9]{11}" required></div><div class="col-md-3"><label class="form-label fw-bold">선박</label><input class="form-control" name="ship" value="<?=bp($ship)?>"></div><div class="col-md-3"><label class="form-label fw-bold">전출항지</label><input class="form-control" name="departure" value="<?=bp($departure)?>"></div><div class="col-md-2"><button class="btn btn-danger w-100">검증화면 이동</button></div></div></form></div></div>
<?php if($mrn!==''):?><div class="card shadow-sm mb-3"><div class="card-body"><div class="d-flex justify-content-between flex-wrap"><b class="fs-5"><?=bp($ship?:'Shanghai 출항 표본')?></b><code><?=bp($mrn)?></code></div><div class="row mt-3 small"><div class="col-md-3"><b>API024 MRN/제출번호</b><br><?=bp($mrn)?></div><div class="col-md-3"><b>실제 전출항지</b><br><?=bp($departure?:'-')?></div><div class="col-md-3"><b>입항일시</b><br><?=bp($arrival?:'-')?></div><div class="col-md-3"><b>현재 상태</b><br><span class="text-danger fw-bold">B/L 또는 cargMtNo 연결 대기</span></div></div><hr><div class="small"><span class="step">API024 ✓</span> → <span class="step">MRN ✓</span> → <span class="step text-danger">MSN/B/L 미확보</span> → API001 대기</div></div></div><?php endif?>
<div class="card shadow-sm mb-3"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2"><div><b>v1.8.6 MSN 실데이터 검증</b><br><span class="small text-secondary">MRN + 0001~0005 후보를 우리 API001로 직접 확인합니다.</span></div><a class="btn btn-danger" href="msn_scan.php?mrn=<?=urlencode($mrn)?>&ship=<?=urlencode($ship)?>&departure=<?=urlencode($departure)?>">MSN 0001~0005 API001 검증</a></div></div>
<div class="alert alert-warning"><b>v1.8.6의 역할:</b> 승인된 데이터 경로에서 B/L 또는 완성된 cargMtNo를 확보했을 때 즉시 API001로 검증합니다. 번호를 추정하지 않으므로, 현재 자동 연결이 안 되는 것은 오류가 아니라 안전장치입니다.</div>
<div class="row g-3"><div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">MBL / HBL → API001</h5><form method="post"><input type="hidden" name="mrn" value="<?=bp($mrn)?>"><input type="hidden" name="ship" value="<?=bp($ship)?>"><input type="hidden" name="arrival" value="<?=bp($arrival)?>"><input type="hidden" name="departure" value="<?=bp($departure)?>"><div class="mb-2"><select class="form-select" name="mode"><option value="mbl">MBL</option><option value="hbl">HBL</option></select></div><div class="mb-2"><input class="form-control" name="bl_no" placeholder="실제 B/L 번호" required></div><div class="mb-3"><input class="form-control" name="bl_year" value="<?=bp(substr($arrival,0,4)?:date('Y'))?>" pattern="20\d{2}" required></div><button class="btn btn-primary">API001 조회</button></form></div></div></div>
<div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">완성된 cargMtNo → API001</h5><form method="post"><input type="hidden" name="mode" value="cargo"><input type="hidden" name="mrn" value="<?=bp($mrn)?>"><input type="hidden" name="ship" value="<?=bp($ship)?>"><input type="hidden" name="arrival" value="<?=bp($arrival)?>"><input type="hidden" name="departure" value="<?=bp($departure)?>"><div class="mb-3"><input class="form-control" name="cargo_no" minlength="15" maxlength="19" placeholder="실제 cargMtNo 15~19자리" required></div><button class="btn btn-primary">API001 조회</button></form><div class="small text-secondary mt-3">MRN 11자리만 입력해서 호출하는 기능은 의도적으로 제공하지 않습니다. 완성된 cargMtNo를 입력할 때는 현재 MRN으로 시작하는지도 먼저 검사합니다.</div></div></div></div></div>
<?php if($error):?><div class="alert alert-danger mt-3"><?=bp($error)?></div><?php endif?>
<?php if(is_array($result)):?><?php if(!empty($result['detail'])) render_row($result['detail']); elseif(isset($result['cargo_no']) && (isset($result['product_name'])||isset($result['ship_name']))) render_row($result); ?><?php if(!empty($result['candidates'])):?><div class="card shadow-sm mt-3"><div class="card-body"><h5 class="fw-bold">다건 후보 <?=count($result['candidates'])?>건</h5><?php foreach($result['candidates'] as $c):?><div class="border rounded p-2 mb-2 small"><b><?=bp((string)($c['cargo_no']??'-'))?></b> · MBL <?=bp((string)($c['mbl_no']??'-'))?> · HBL <?=bp((string)($c['hbl_no']??'-'))?> · <?=bp((string)($c['entry_date']??'-'))?></div><?php endforeach?></div></div><?php endif?><?php endif?>
<div class="card shadow-sm mt-3"><div class="card-body"><h5 class="fw-bold">현재 연결상태</h5><div class="small">확정: <b>Shanghai 실제 전출항지 + API024 MRN</b><br>미확정: <b>MRN → MSN/B/L/cargMtNo</b><br>다음 개발 조건: UNI-PASS에서 MRN을 받아 B/L/MSN/cargMtNo를 반환하는 <b>실제 승인 가능한 인터페이스의 요청·응답 명세</b>가 확인되면 이 구간을 자동화합니다.</div></div></div>
</div><script>
function setGlovisChorus(){
  const f=document.querySelector('form[action="msn_scan.php"]');
  if(!f) return;
  f.querySelector('[name="mrn"]').value='26GLVSP304I';
  f.querySelector('[name="ship"]').value='GLOVIS CHORUS';
  f.querySelector('[name="departure"]').value='Shanghai (CNSHA)';
}
</script></body></html>
