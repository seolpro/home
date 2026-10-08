<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/lib/common.php';
tp_admin_required();
$pdo=tp_db();
$rows=$pdo->query("SELECT v.*,(SELECT COUNT(*) FROM tp_manifest_items m WHERE m.vessel_id=v.id) manifest_item_count,(SELECT COUNT(*) FROM tp_manifest_items m WHERE m.vessel_id=v.id AND m.tesla_flag=1) tesla_item_count FROM tp_vessels v WHERE v.is_vehicle_carrier=1 AND v.customs_iopr_verified=1 AND v.china_origin_flag=1 AND v.customs_iopr_submission_no REGEXP '^[A-Za-z0-9]{11}$' ORDER BY COALESCE(v.eta,v.first_seen_at) DESC LIMIT 200")->fetchAll();
function track_status(array $r): array{
 if((int)$r['tesla_flag']===1) return ['TESLA 확인','success'];
 if(!empty($r['manifest_scan_error'])) return ['조회오류','danger'];
 if(!empty($r['manifest_scan_last_checked_at']) && (int)$r['manifest_item_count']>0) return ['Tesla 아님','secondary'];
 if(!empty($r['manifest_scan_last_checked_at']) && (int)$r['manifest_scan_hits']===0) return ['화물 대기','warning'];
 return ['추적 대기','info'];
}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Shanghai 추적대상</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f6f8}.card{border:0;border-radius:18px}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}</style></head><body><div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="fw-bold mb-1">Shanghai 추적대상</h2><div class="text-secondary">v1.9.2 · API024 Shanghai 확인 → MRN 유지 → API001 반복 추적</div></div><div><a class="btn btn-outline-success" href="manifest_items.php">자동 화물탐색</a> <a class="btn btn-outline-dark" href="index.php">관리자</a></div></div>
<div class="alert alert-info"><b>운영 방식:</b> Shanghai 실제 전출항지와 11자리 MRN이 확인된 선박은 DB에 계속 유지합니다. 화물이 아직 없으면 <b>화물 대기</b>로 남고, 크론 실행 때 다시 검사합니다. API001에서 실제 <b>TESLA</b> 품명이 확인되어야 TESLA 확인으로 바뀝니다.</div>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary">전체 추적대상</div><div class="fs-3 fw-bold"><?=count($rows)?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary">TESLA 확인</div><div class="fs-3 fw-bold"><?=count(array_filter($rows,fn($r)=>(int)$r['tesla_flag']===1))?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary">대기/기타</div><div class="fs-3 fw-bold"><?=count(array_filter($rows,fn($r)=>(int)$r['tesla_flag']!==1))?></div></div></div></div></div>
<div class="card shadow-sm"><div class="card-body table-responsive"><table class="table align-middle"><thead><tr><th>상태</th><th>선박 / 입항</th><th>실제 전출항지</th><th>MRN</th><th>화물</th><th>최근 스캔</th><th>TESLA 정보</th></tr></thead><tbody>
<?php foreach($rows as $r): [$label,$color]=track_status($r);?><tr><td><span class="badge text-bg-<?=$color?>"><?=$label?></span></td><td><b><?=h($r['vessel_name'])?></b><div class="small text-secondary"><?=h($r['eta'])?></div></td><td><?=h($r['customs_iopr_departure_port'])?></td><td class="mono"><?=h($r['customs_iopr_submission_no'])?></td><td>실제 <?=h($r['manifest_item_count'])?>건<div class="small text-secondary">마지막 MSN <?=h($r['manifest_scan_last_msn'])?></div></td><td><?=h($r['manifest_scan_last_checked_at']?:'아직 없음')?><?php if($r['manifest_scan_error']):?><div class="small text-danger"><?=h($r['manifest_scan_error'])?></div><?php endif?></td><td><?php if((int)$r['tesla_flag']===1):?><b><?=h($r['customs_product_name']?:'TESLA')?></b><div class="small"><?=h($r['vehicle_count'])?>대 · <?=h($r['total_weight_kg'])?>kg</div><div class="small"><?=h($r['model_guess'])?> · <?=h($r['cargo_status'])?></div><?php else:?>-<?php endif?></td></tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="7" class="text-center text-secondary py-5">현재 저장된 Shanghai 추적대상이 없습니다. 새로운 Shanghai 출항 자동차운반선이 API024에서 확인되면 자동으로 여기에 나타납니다.</td></tr><?php endif?></tbody></table></div></div>
<div class="alert alert-warning mt-3 mb-0"><b>알림톡 안전장치:</b> PPURIO_ENABLED=false 동안에는 <code>notified_at</code>을 기록하지 않습니다. 따라서 테스트 중 발견된 TESLA 건이 나중에 알림톡을 켰을 때 발송대상에서 사라지는 문제를 막았습니다.</div>
</div></body></html>
