<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/lib/common.php';
require_once dirname(__DIR__).'/lib/customs.php';
tp_admin_required();
function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
// POST must win over the query string. Otherwise a page opened from Shanghai history
// keeps restoring the old GET MRN after pressing the validation button.
$mrn=strtoupper(trim((string)($_POST['mrn']??$_GET['mrn']??'')));
$ship=trim((string)($_POST['ship']??$_GET['ship']??''));
$departure=trim((string)($_POST['departure']??$_GET['departure']??''));
$rows=[];$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{$rows=tp_customs_scan_msn($mrn,1,5);}catch(Throwable $x){$error=$x->getMessage();}
}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MSN 0001~0005 API001 검증</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f6f8}.card{border:0;border-radius:16px}code{word-break:break-all}</style></head><body><div class="container py-4" style="max-width:1200px">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h2 class="fw-bold mb-1">MRN → MSN 실데이터 검증</h2><div class="text-secondary">v1.8.6.2 · POST 입력값 우선 유지 + API001 0001~0005 제한 검증</div></div><div><a class="btn btn-outline-dark" href="mrn_bridge.php?mrn=<?=urlencode($mrn)?>&ship=<?=urlencode($ship)?>&departure=<?=urlencode($departure)?>">MRN 실험실</a> <a class="btn btn-outline-dark" href="shanghai_history.php">Shanghai 이력</a></div></div>
<div class="alert alert-info mt-4"><b>이번 검증의 의미:</b> EV Diary의 실제 표본 <code>26GLVSP304I + MSN 0001 = 26GLVSP304I0001</code>에서 API001 화물이 확인된 사실을 바탕으로, <b>우리에게 승인된 API001 키</b>로 현재 MRN의 MSN 0001~0005를 제한적으로 조회합니다. <b>응답이 실제로 존재하는 번호만 유효 화물로 인정</b>합니다.</div>
<div class="card shadow-sm mb-3"><div class="card-body">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><b>MRN 직접 입력</b><div class="small text-secondary">Shanghai 이력에 없는 선박도 MRN을 직접 입력해 검증할 수 있습니다.</div></div><button type="button" class="btn btn-outline-primary btn-sm" onclick="setGlovisChorus()">GLOVIS CHORUS 테스트값 넣기</button></div>
<form method="post" action="msn_scan.php"><div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label fw-bold">MRN 11자리</label><input class="form-control" name="mrn" value="<?=e($mrn)?>" maxlength="11" required></div><div class="col-md-3"><label class="form-label fw-bold">선박</label><input class="form-control" name="ship" value="<?=e($ship)?>"></div><div class="col-md-3"><label class="form-label fw-bold">전출항지</label><input class="form-control" name="departure" value="<?=e($departure)?>"></div><div class="col-md-2"><button class="btn btn-danger w-100">0001~0005 검증</button></div></div></form><div class="small text-secondary mt-3">자동 크론에는 아직 적용하지 않습니다. 관리자 수동 진단에서 5건만 호출합니다.</div></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?>
<?php if($rows): $valid=array_values(array_filter($rows,fn($x)=>$x['valid'])); ?>
<div class="alert <?=count($valid)?'alert-success':'alert-warning'?>"><b>검증 완료:</b> API001 <?=count($rows)?>건 호출 · 실제 화물 응답 <b><?=count($valid)?>건</b><?php if(count($valid)):?> · <?php $t=array_filter($valid,fn($x)=>!empty($x['result']['tesla_match']));?><b>TESLA <?=count($t)?>건</b><?php endif?></div>
<div class="card shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>MSN</th><th>cargMtNo</th><th>API001</th><th>품명</th><th>MBL</th><th>수량</th><th>총중량</th><th>적재항</th><th>통관상태</th></tr></thead><tbody>
<?php foreach($rows as $x):$r=$x['result'];?><tr><td><b><?=e($x['msn'])?></b></td><td><code><?=e($x['cargo_no'])?></code></td><td><?=$x['valid']?'<span class="badge text-bg-success">실제 응답</span>':'<span class="badge text-bg-secondary">없음</span>'?></td><td><?=!empty($r['tesla_match'])?'<span class="badge text-bg-danger">TESLA</span> ':''?><?=e((string)($r['product_name']??'-'))?></td><td><?=e((string)($r['mbl_no']??'-'))?></td><td><?=e((string)($r['package_count']??'-'))?></td><td><?=e((string)($r['total_weight']??'-'))?> <?=e((string)($r['weight_unit']??''))?></td><td><?=e((string)($r['loading_port']??'-'))?></td><td><?=e((string)($r['progress_status']??'-'))?></td></tr><?php endforeach?>
</tbody></table></div></div></div>
<?php endif?>
<div class="alert alert-warning mt-3"><b>안전장치:</b> 0001~0005가 항상 존재한다고 가정하지 않습니다. 번호 조합은 후보 생성에만 사용하며 <b>API001이 실제 화물정보를 반환한 경우에만</b> 연결 성공으로 판정합니다. 이번 결과가 확인되기 전에는 자동 수집·알림톡 발송에 반영하지 않습니다.</div>
</div><script>
function setGlovisChorus(){
  const f=document.querySelector('form[method="post"]');
  if(!f) return;
  f.querySelector('[name="mrn"]').value='26GLVSP304I';
  f.querySelector('[name="ship"]').value='GLOVIS CHORUS';
  f.querySelector('[name="departure"]').value='Shanghai (CNSHA)';
}
</script></body></html>
