<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/common.php';

$pdo = tp_db();

// 공개 화면에는 ① API024로 Shanghai 출항이 검증된 추적대상 ② API001에서 TESLA가 확인된 건만 노출합니다.
$confirmedSt = $pdo->query("SELECT * FROM tp_vessels
  WHERE tesla_flag=1
    AND is_vehicle_carrier=1
    AND customs_iopr_verified=1
    AND china_origin_flag=1
  ORDER BY COALESCE(eta,customs_iopr_arrival_at,first_seen_at) DESC
  LIMIT 30");
$confirmed = $confirmedSt->fetchAll(PDO::FETCH_ASSOC);

$trackingSt = $pdo->query("SELECT * FROM tp_vessels
  WHERE tesla_flag=0
    AND is_vehicle_carrier=1
    AND customs_iopr_verified=1
    AND china_origin_flag=1
    AND customs_iopr_submission_no REGEXP '^[A-Za-z0-9]{11}$'
    AND (eta IS NULL OR eta >= DATE_SUB(NOW(), INTERVAL 7 DAY))
  ORDER BY COALESCE(eta,customs_iopr_arrival_at,first_seen_at) ASC
  LIMIT 30");
$tracking = $trackingSt->fetchAll(PDO::FETCH_ASSOC);

$confirmedCount = count($confirmed);
$trackingCount = count($tracking);
$vehicleTotal = 0;
foreach ($confirmed as $r) $vehicleTotal += (int)($r['vehicle_count'] ?? 0);

$lastChecked = $pdo->query("SELECT MAX(customs_iopr_last_checked_at) FROM tp_vessels WHERE is_vehicle_carrier=1")->fetchColumn();
if (!$lastChecked) $lastChecked = $pdo->query("SELECT MAX(updated_at) FROM tp_vessels")->fetchColumn();

function tp_public_dt($v): string {
    if (!$v) return '확인 중';
    $ts = strtotime((string)$v);
    return $ts ? date('Y.m.d H:i', $ts) : (string)$v;
}
function tp_public_num($v, int $dec=0): string {
    if ($v === null || $v === '') return '-';
    return number_format((float)$v, $dec);
}
function tp_public_origin(array $r): string {
    return trim((string)($r['customs_iopr_departure_port'] ?? '')) ?: 'Shanghai';
}
function tp_public_status(array $r): string {
    return trim((string)($r['customs_progress_status'] ?? '')) ?: (trim((string)($r['cargo_status'] ?? '')) ?: '화물정보 확인');
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tesla Port · 상하이 출항 테슬라 입항 모니터</title>
<meta name="description" content="Shanghai 출항 자동차운반선과 공개 화물정보를 이용한 Tesla 국내 입항 모니터">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--ink:#111;--muted:#69707a;--line:#e8eaed;--soft:#f5f6f8;--red:#e82127;--green:#198754;--amber:#b77900}
body{background:var(--soft);color:var(--ink);font-family:system-ui,-apple-system,"Noto Sans KR",sans-serif}
.wrap{max-width:980px}.hero{background:#111;color:#fff;border-radius:28px;padding:34px 34px 30px;position:relative;overflow:hidden}.hero:after{content:"";position:absolute;width:220px;height:220px;border:42px solid rgba(232,33,39,.18);border-radius:50%;right:-75px;top:-85px}.eyebrow{font-size:.78rem;letter-spacing:.16em;color:#aaa}.hero h1{font-size:clamp(2rem,5vw,3.2rem);letter-spacing:-.04em}.live{display:inline-flex;align-items:center;gap:7px;background:#252525;border-radius:999px;padding:7px 11px;font-size:.82rem}.dot{width:8px;height:8px;border-radius:50%;background:#42d47b;box-shadow:0 0 0 4px rgba(66,212,123,.12)}
.stat{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px}.stat .n{font-size:1.55rem;font-weight:800}.section-title{font-size:1.05rem;font-weight:800;margin:30px 2px 13px}.vcard{border:0;border-radius:22px;box-shadow:0 5px 22px rgba(0,0,0,.055);overflow:hidden}.vcard.confirmed{border-left:5px solid var(--red)}.vcard.tracking{border-left:5px solid #e0a000}.route{font-size:.95rem;color:#50555b}.ship{font-size:1.45rem;font-weight:800;letter-spacing:-.02em}.pill{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:.78rem;font-weight:700}.pill-red{background:#fff0f1;color:#c4171c}.pill-amber{background:#fff7df;color:#8a6200}.pill-gray{background:#f1f2f4;color:#555}.metric{background:#f8f9fa;border-radius:14px;padding:12px 13px;height:100%}.metric .label{font-size:.75rem;color:#7a8087}.metric .value{font-weight:800;margin-top:2px}.empty{background:#fff;border:1px dashed #cfd3d8;border-radius:22px;padding:42px 24px;text-align:center}.empty .icon{font-size:2.2rem}.footer-note{color:#737980;font-size:.82rem;line-height:1.65}.update{color:#bfc2c6;font-size:.82rem}@media(max-width:576px){.hero{padding:27px 22px}.stat{padding:14px}.ship{font-size:1.25rem}}
</style>
</head>
<body>
<div class="container wrap py-4 py-md-5">
  <section class="hero mb-3">
    <div class="eyebrow mb-2">SHANGHAI → KOREA</div>
    <h1 class="fw-bold mb-1">TESLA PORT</h1>
    <div class="text-white-50 mb-4">테슬라 국내 입항 · 통관 모니터</div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="live"><span class="dot"></span> 자동 모니터링 중</span>
      <span class="update">마지막 확인 <?=h(tp_public_dt($lastChecked))?></span>
    </div>
  </section>

  <div class="row g-2 g-md-3">
    <div class="col-4"><div class="stat"><div class="small text-secondary">추적 중</div><div class="n"><?=number_format($trackingCount)?> <small class="fs-6">척</small></div></div></div>
    <div class="col-4"><div class="stat"><div class="small text-secondary">Tesla 확인</div><div class="n"><?=number_format($confirmedCount)?> <small class="fs-6">척</small></div></div></div>
    <div class="col-4"><div class="stat"><div class="small text-secondary">확인 차량</div><div class="n"><?=number_format($vehicleTotal)?> <small class="fs-6">대</small></div></div></div>
  </div>

  <?php if ($tracking): ?>
  <div class="section-title">Shanghai 출항 · 화물정보 확인 중</div>
  <?php foreach ($tracking as $r): ?>
  <article class="card vcard tracking mb-3"><div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
      <div><span class="pill pill-amber mb-2">적하목록 추적 중</span><div class="ship"><?=h($r['vessel_name'])?></div><div class="route mt-1"><?=h(tp_public_origin($r))?> → <?=h($r['port_name'] ?: '평택')?></div></div>
      <div class="text-md-end"><div class="small text-secondary">입항 예정</div><strong><?=h(tp_public_dt($r['eta'] ?: $r['customs_iopr_arrival_at']))?></strong></div>
    </div>
    <div class="mt-3 small text-secondary">API024에서 Shanghai 출항이 확인되었습니다. Tesla 화물 여부는 공개 적하목록을 계속 확인하고 있습니다.</div>
  </div></article>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="section-title">Tesla 확인 입항정보</div>
  <?php if (!$confirmed): ?>
    <div class="empty mb-3"><div class="icon mb-2">🚢</div><h5 class="fw-bold">현재 확인된 Tesla 입항정보가 없습니다.</h5><div class="text-secondary">Shanghai 출항 자동차운반선과 적하목록을 자동으로 모니터링하고 있습니다.</div></div>
  <?php else: ?>
    <?php foreach ($confirmed as $r):
      $qty=(int)($r['vehicle_count']??0); $kg=(float)($r['total_weight_kg']??$r['customs_total_weight']??0); $avg=($qty>0&&$kg>0)?$kg/$qty:0;
    ?>
    <article class="card vcard confirmed mb-3"><div class="card-body p-4">
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3">
        <div><span class="pill pill-red mb-2">TESLA 화물 확인</span><div class="ship"><?=h($r['vessel_name'])?></div><div class="route mt-1"><?=h(tp_public_origin($r))?> → <?=h($r['port_name'] ?: '평택')?></div></div>
        <div class="text-md-end"><div class="small text-secondary">입항일시</div><strong><?=h(tp_public_dt($r['eta'] ?: $r['customs_iopr_arrival_at']))?></strong></div>
      </div>
      <div class="row g-2">
        <div class="col-6 col-md-3"><div class="metric"><div class="label">차량수량</div><div class="value"><?=tp_public_num($qty)?>대</div></div></div>
        <div class="col-6 col-md-3"><div class="metric"><div class="label">예상모델</div><div class="value"><?=h($r['model_guess'] ?: '확인 중')?></div></div></div>
        <div class="col-6 col-md-3"><div class="metric"><div class="label">총중량</div><div class="value"><?=$kg>0?tp_public_num($kg).' kg':'-'?></div></div></div>
        <div class="col-6 col-md-3"><div class="metric"><div class="label">평균중량</div><div class="value"><?=$avg>0?tp_public_num(round($avg)).' kg/대':'-'?></div></div></div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3"><span class="pill pill-gray">통관 · <?=h(tp_public_status($r))?></span><?php if(!empty($r['customs_iopr_submission_no'])):?><span class="pill pill-gray">MRN <?=h($r['customs_iopr_submission_no'])?></span><?php endif;?></div>
    </div></article>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="footer-note mt-4 px-1">공개 선박·관세 화물정보를 기반으로 제공합니다. Tesla 여부는 화물 품명으로 확인하며, 모델 정보는 차량수량과 총중량 등을 이용한 추정값이 포함될 수 있습니다. Shanghai 출항이 확인되지 않은 일반 자동차운반선은 공개 화면에 표시하지 않습니다.</div>
</div>
</body></html>
