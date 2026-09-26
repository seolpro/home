<?php
declare(strict_types=1);
require __DIR__.'/config.php';

$data = load_data();

function evidence_web_file(string $file): string {
    $file = ltrim(str_replace('\\', '/', $file), '/');
    if ($file === '' || strpos($file, '..') !== false) return $file;
    if (strpos($file, 'uploads/') === 0) {
        $rel = substr($file, strlen('uploads/'));
        $web = 'uploads/web/' . $rel;
        if (is_file(__DIR__ . '/' . $web)) return $web;
    }
    return $file;
}

$spots = [
  1=>['title'=>'350번지 ↔ 336번지 경계','claim'=>'별지 1 도면 표시 ㄴ 부분 지상 약 9㎡ 그물망 펜스 제거 및 ㄱ 부분 지상 약 40㎡ 수로 제거 관련','point'=>'현재 경계부 시설의 실제 존치 여부와 현장 상태를 사진으로 확인'],
  2=>['title'=>'336번지 ↔ 334번지 인접부','claim'=>'별지 5 도면 표시 지상 약 5㎡ 그물망 펜스 제거 관련','point'=>'해당 위치의 현존 시설 및 경계부 상태 확인'],
  3=>['title'=>'334번지 북측 경계부','claim'=>'별지 4-1 도면 표시 그물망 등 피고 설치시설 철거 관련','point'=>'원고가 특정한 시설이 현재 실제로 존재하는지 여부 확인'],
  4=>['title'=>'350·334 경계 및 시설물 구간','claim'=>'차수용 철판, PE관 150mm, 철판·그물망 등 철거 청구 관련','point'=>'각 시설의 위치·현재 상태·철거 여부를 현장사진으로 특정'],
  5=>['title'=>'334번지 자연 유수·배수 구간','claim'=>'334번지 안으로 자연 유하할 수 있는 배수로 설치 청구 관련','point'=>'자연구배, 실제 유수방향, 하단 농로 및 배수로 통수·폐색 상태를 연속 촬영하여 확인'],
];
$by=[];
foreach(($data['items']??[]) as $it){
    $spot=(int)($it['spot']??0);
    if($spot>0) $by[$spot][]=$it;
}
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h(APP_TITLE)?> - 증거사진 인쇄</title>
<style>
@page{size:A4 portrait;margin:12mm 11mm 14mm}
*{box-sizing:border-box}
body{margin:0;background:#e9edf3;color:#172033;font-family:system-ui,-apple-system,"Noto Sans KR",sans-serif}
.wrap{max-width:210mm;margin:0 auto;background:#fff;padding:12mm}
.toolbar{position:sticky;top:0;z-index:10;display:flex;gap:8px;justify-content:flex-end;padding:10px;background:#172033}
.btn{border:0;border-radius:9px;padding:10px 14px;font-weight:800;cursor:pointer;text-decoration:none;background:#fff;color:#172033}
h1{font-size:25px;margin:0 0 6px}
.sub{color:#657083;font-size:13px;margin-bottom:14px}
.summary{border:1px solid #ccd4df;border-radius:10px;padding:12px;margin-bottom:14px;background:#f8fafc}
.mapgrid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px}
.map{border:1px solid #d9e0e8;border-radius:8px;padding:8px}
.map img{width:100%;max-height:245px;object-fit:contain;display:block}
.cap{font-size:11px;color:#667085;margin-top:5px}
.spot{margin-top:18px;border-top:3px solid #172033;padding-top:10px}
.spot-title{display:flex;align-items:center;gap:9px;margin-bottom:8px}
.num{width:30px;height:30px;border-radius:7px;background:#1f5eff;color:#fff;display:grid;place-items:center;font-weight:900}
h2{font-size:18px;margin:0}
.box{font-size:12px;line-height:1.55;padding:8px 10px;border-radius:7px;margin:6px 0}
.claim{background:#f5f6f8;border-left:4px solid #8b98aa}
.point{background:#eef5ff;border-left:4px solid #1f5eff}
.photo-sheet{page-break-inside:avoid;break-inside:avoid;margin:12px 0 16px;border:1px solid #cfd7e2;border-radius:8px;padding:8px}
.photo-head{font-size:14px;font-weight:900;margin:0 0 7px}
.photo-sheet img{display:block;width:100%;height:auto;max-height:182mm;object-fit:contain;background:#f4f5f7}
.info{width:100%;border-collapse:collapse;margin-top:7px;font-size:11px;line-height:1.45}
.info th,.info td{border:1px solid #d7dde6;padding:5px 7px;vertical-align:top}
.info th{width:21%;background:#f6f8fa;text-align:left}
.empty{padding:14px;border:1px dashed #bcc5d1;color:#667085;text-align:center}
.hash{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;word-break:break-all;font-size:9px}
.footer-note{font-size:10px;color:#667085;border-top:1px solid #ddd;margin-top:20px;padding-top:8px}
@media print{
  body{background:#fff}
  .toolbar{display:none}
  .wrap{max-width:none;margin:0;padding:0}
  .mapgrid{break-inside:avoid}
  .spot{break-before:auto}
  .photo-sheet{break-inside:avoid;page-break-inside:avoid}
  a{color:inherit;text-decoration:none}
}
@media(max-width:700px){.wrap{padding:12px}.mapgrid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="toolbar">
  <a class="btn" href="index.php">← 현장증거 화면</a>
  <button class="btn" onclick="window.print()">인쇄 / PDF 저장</button>
</div>
<main class="wrap">
  <h1><?=h(APP_TITLE)?> · 증거사진 전체목록</h1>
  <div class="sub">등록된 현장사진을 SPOT별로 모두 펼쳐 표시하는 인쇄/PDF 전용 화면입니다.</div>

  <div class="summary">
    <b>자료 구성</b><br>
    기준 지형도와 유수 흐름 참고도에 이어 SPOT 1~5의 쟁점, 확인 목적 및 등록된 현장사진 전체를 순서대로 표시합니다.
    사진 원본은 기존 <code>uploads/</code>, 설명자료는 기존 <code>data/evidence.json</code>을 그대로 사용합니다.
  </div>

  <div class="mapgrid">
    <div class="map">
      <img src="assets/base-map.jpg" alt="기준 지형도">
      <div class="cap">기준 지형도 · 별지 4-1 참고</div>
    </div>
    <div class="map">
      <img src="assets/flow-map.jpg" alt="유수 흐름 참고도">
      <div class="cap">유수 흐름 참고도 · 별지 4-2 참고</div>
    </div>
  </div>

<?php foreach($spots as $n=>$s): ?>
<section class="spot">
  <div class="spot-title"><span class="num"><?=$n?></span><h2>SPOT <?=$n?> · <?=h($s['title'])?></h2></div>
  <div class="box claim"><b>원고 청구·쟁점 정리</b><br><?=h($s['claim'])?></div>
  <div class="box point"><b>현장사진의 확인 목적</b><br><?=h($s['point'])?></div>

  <?php if(empty($by[$n])): ?>
    <div class="empty">등록된 현장사진이 없습니다.</div>
  <?php else: ?>
    <?php $seq=1; foreach($by[$n] as $it): ?>
      <article class="photo-sheet">
        <div class="photo-head"><?=h($it['title'] ?: ('사진 '.$n.'-'.$seq))?></div>
        <img src="<?=h(evidence_web_file((string)$it['file']))?>" alt="<?=h($it['title'] ?: ('SPOT '.$n.' 현장사진'))?>" loading="lazy" decoding="async">
        <table class="info">
          <tr><th>사진번호</th><td>SPOT <?=$n?>-<?=$seq?></td><th>촬영일시</th><td><?=h($it['taken_at']??'-')?></td></tr>
          <tr><th>촬영방향</th><td colspan="3"><?=h($it['direction']??'-')?></td></tr>
          <tr><th>현장관찰</th><td colspan="3"><?=nl2br(h($it['observation']??'-'))?></td></tr>
          <tr><th>입증취지</th><td colspan="3"><?=nl2br(h($it['purpose']??'-'))?></td></tr>
          <tr><th>SHA-256</th><td colspan="3" class="hash"><?=h($it['sha256']??'-')?></td></tr>
        </table>
      </article>
    <?php $seq++; endforeach; ?>
  <?php endif; ?>
</section>
<?php endforeach; ?>

<div class="footer-note">
※ 이 화면은 등록자료를 인쇄·PDF 저장하기 위한 정리용 화면입니다. 사진에서 직접 확인되는 사실과 당사자의 주장·법률적 평가는 구분하여 최종 제출자료를 작성하시기 바랍니다.
</div>
</main>
</body>
</html>
