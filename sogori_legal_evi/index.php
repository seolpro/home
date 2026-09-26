<?php
declare(strict_types=1);
require __DIR__.'/config.php';
$data=load_data();
$spots=[
  1=>['title'=>'350번지 ↔ 336번지 경계','claim'=>'별지 1 도면 표시 ㄴ 부분 지상 약 9㎡ 그물망 펜스 제거 및 ㄱ 부분 지상 약 40㎡ 수로 제거 관련','point'=>'현재 경계부 시설의 실제 존치 여부와 현장 상태를 사진으로 확인'],
  2=>['title'=>'336번지 ↔ 334번지 인접부','claim'=>'별지 5 도면 표시 지상 약 5㎡ 그물망 펜스 제거 관련','point'=>'해당 위치의 현존 시설 및 경계부 상태 확인'],
  3=>['title'=>'334번지 북측 경계부','claim'=>'별지 4-1 도면 표시 그물망 등 피고 설치시설 철거 관련','point'=>'원고가 특정한 시설이 현재 실제로 존재하는지 여부 확인'],
  4=>['title'=>'350·334 경계 및 시설물 구간','claim'=>'차수용 철판, PE관 150mm, 철판·그물망 등 철거 청구 관련','point'=>'각 시설의 위치·현재 상태·철거 여부를 현장사진으로 특정'],
  5=>['title'=>'334번지 자연 유수·배수 구간','claim'=>'334번지 안으로 자연 유하할 수 있는 배수로 설치 청구 관련','point'=>'자연구배, 실제 유수방향, 하단 농로 및 배수로 통수·폐색 상태를 연속 촬영하여 확인'],
];
$by=[]; foreach(($data['items']??[]) as $it){$by[(int)$it['spot']][]=$it;}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h(APP_TITLE)?></title>
<style>
:root{--ink:#172033;--muted:#657083;--line:#dde3ec;--blue:#1f5eff;--paper:#fff;--bg:#f4f6f9;--red:#b42318}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,"Noto Sans KR",sans-serif}.wrap{max-width:1180px;margin:auto;padding:22px}.hero{background:#172033;color:white;border-radius:18px;padding:26px 28px;margin-bottom:18px}.hero h1{font-size:clamp(24px,4vw,38px);margin:0 0 8px}.hero p{margin:5px 0;color:#d8deea}.badge{display:inline-block;background:#eef3ff;color:#234fc4;border-radius:999px;padding:7px 11px;font-size:13px;font-weight:800}.grid{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}.card{background:white;border:1px solid var(--line);border-radius:16px;padding:18px;box-shadow:0 5px 20px rgba(20,30,55,.05)}h2{font-size:20px;margin:0 0 13px}.mapbox{position:relative;border-radius:13px;overflow:hidden;background:#eef1f5}.mapbox img{display:block;width:100%;height:auto}.compass{position:absolute;right:16px;top:16px;width:94px;height:94px;border-radius:50%;background:rgba(255,255,255,.94);box-shadow:0 3px 14px #0002;display:grid;place-items:center;font-weight:900}.compass:before{content:'▲';position:absolute;top:7px;color:#c52b2b;font-size:24px}.compass .n{position:absolute;top:30px}.compass .s{position:absolute;bottom:8px}.compass .w{position:absolute;left:10px}.compass .e{position:absolute;right:10px}.landtag{position:absolute;padding:6px 9px;border-radius:8px;background:rgba(23,32,51,.88);color:#fff;font-weight:900;font-size:13px}.north350{right:16%;top:27%}.south334{left:43%;bottom:24%}.orientation{margin-top:10px;padding:11px 13px;background:#fff8e8;border:1px solid #f0d58c;border-radius:10px;font-weight:800}.spotnav{display:flex;gap:7px;flex-wrap:wrap;margin:12px 0}.spotnav a{text-decoration:none}.spot{margin-top:18px;scroll-margin-top:12px}.spothead{display:flex;align-items:center;gap:10px}.num{width:34px;height:34px;border-radius:10px;background:var(--blue);color:#fff;display:grid;place-items:center;font-weight:900}.claim{background:#f7f9fc;border-left:4px solid #9aa8bf;padding:11px 13px;margin:12px 0;border-radius:8px}.point{background:#eef5ff;border-left:4px solid var(--blue);padding:11px 13px;margin:12px 0;border-radius:8px}.photos{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}.photo{border:1px solid var(--line);border-radius:12px;overflow:hidden;background:#fff}.photo button{border:0;padding:0;background:none;width:100%;cursor:zoom-in}.photo img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block}.meta{padding:10px;font-size:13px}.meta b{display:block;margin-bottom:4px}.empty{color:var(--muted);padding:18px;border:1px dashed #cbd3df;border-radius:10px;text-align:center}.small{font-size:13px;color:var(--muted)}.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;text-decoration:none;border:0;border-radius:10px;padding:10px 13px;font-weight:800;background:#172033;color:white;cursor:pointer}.btn.alt{background:#eef2f7;color:#172033}@media(max-width:850px){.grid{grid-template-columns:1fr}.wrap{padding:12px}.compass{width:78px;height:78px}.north350,.south334{font-size:11px}}
#modal{display:none;position:fixed;inset:0;background:#000d;z-index:1000;padding:18px;align-items:center;justify-content:center}#modal.open{display:flex}.modalbox{max-width:1100px;max-height:94vh;background:#fff;border-radius:14px;overflow:auto;position:relative}.modalbox img{max-width:100%;display:block}.modalcap{padding:14px}.close{position:fixed;right:22px;top:16px;color:#fff;font-size:18px;font-weight:900;background:#172033;border:2px solid #fff;border-radius:999px;padding:10px 14px;cursor:pointer;z-index:1002;box-shadow:0 4px 18px #0006}.close:hover{background:#2b3955}@media print{body{background:#fff}.hero{color:#000;background:#fff;border:1px solid #bbb}.toolbar,.spotnav{display:none}.card{box-shadow:none;break-inside:avoid}.grid{display:block}.mapbox{max-width:780px;margin:auto}.spot{break-inside:avoid}}
</style></head><body><main class="wrap">
<section class="hero"><span class="badge">변론 보조 · 현장증거 정리</span><h1><?=h(APP_TITLE)?></h1><p>기준방향: 화면 위쪽 = 북쪽 / 아래쪽 = 남쪽</p><p><b>350번지는 북측, 334번지는 남측</b>에 위치하는 관계를 기준으로 현장사진과 쟁점을 정리합니다.</p><div class="toolbar"><a class="btn" href="admin/login.php">사진 관리</a><button class="btn alt" onclick="window.print()">인쇄 / PDF</button></div></section>
<div class="grid"><section class="card"><h2>① 기준 지형도 · 분쟁 위치</h2><div class="mapbox"><img src="assets/base-map.jpg" alt="피고설치 시설물 위치도"><div class="compass"><span class="n">N 북</span><span class="s">S 남</span><span class="w">W</span><span class="e">E</span></div><span class="landtag north350">350번지 · 북측</span><span class="landtag south334">334번지 · 남측</span></div><div class="orientation">방위 기준: ↑ 북(N) · ↓ 남(S) · 350번지 = 북측 / 334번지 = 남측</div><p class="small">※ 기준 도면은 제출된 별지 4-1 ‘피고설치 시설물 위치도’를 바탕으로 표시했습니다. 실제 경계·방위의 법적 확정은 측량자료 등 원자료에 따릅니다.</p></section>
<section class="card"><h2>② 유수 흐름 참고도</h2><div class="mapbox"><img src="assets/flow-map.jpg" alt="토사측구 평면도와 유로선"></div><p class="small">당초 유로선과 현재 유로선, 토사측구·배수불량 표시가 포함된 별지 4-2를 현장사진 대조용으로 사용합니다.</p><div class="spotnav"><?php foreach($spots as $n=>$s):?><a class="badge" href="#spot<?=$n?>">SPOT <?=$n?></a><?php endforeach?></div></section></div>
<?php foreach($spots as $n=>$s):?><section class="card spot" id="spot<?=$n?>"><div class="spothead"><span class="num"><?=$n?></span><h2><?=h($s['title'])?></h2></div><div class="claim"><b>원고 청구·쟁점 정리</b><br><?=h($s['claim'])?></div><div class="point"><b>현장사진의 확인 목적</b><br><?=h($s['point'])?></div><div class="photos"><?php if(empty($by[$n])):?><div class="empty">아직 등록된 현장사진이 없습니다.<br>관리자에서 오늘 촬영사진을 업로드하세요.</div><?php else: foreach($by[$n] as $it):?><article class="photo"><button onclick='openPhoto(<?=json_encode($it,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT)?>)'><img src="<?=h($it['file'])?>" alt="현장사진"></button><div class="meta"><b><?=h($it['title']?:('SPOT '.$n.' 현장사진'))?></b><?=h($it['taken_at']??'')?> · <?=h($it['direction']??'')?><br><span class="small"><?=h($it['purpose']??'')?></span></div></article><?php endforeach; endif?></div></section><?php endforeach?>
<section class="card"><h2>자료 이용 시 유의</h2><p class="small">이 페이지는 변호인과 당사자가 현장자료를 위치·쟁점별로 정리하기 위한 보조자료입니다. 사진에서 직접 확인되는 사실과 당사자의 법률상 주장·평가는 구분하여 기재하는 것을 전제로 합니다. 원본 사진은 별도로 보존하고 제출 여부와 입증취지는 담당 변호사와 최종 확인하세요.</p></section>
</main><div id="modal" onclick="if(event.target===this)closePhoto()"><button class="close" onclick="closePhoto()" aria-label="사진 닫기">×&nbsp; 닫기</button><div class="modalbox"><img id="mimg"><div class="modalcap" id="mcap"></div></div></div><script>
function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
let photoHistoryActive=false;
function renderPhoto(x){
  document.getElementById('mimg').src=x.file;
  document.getElementById('mcap').innerHTML='<b>'+esc(x.title||'현장사진')+'</b><br>촬영일시: '+esc(x.taken_at||'-')+'<br>촬영방향: '+esc(x.direction||'-')+'<br>현장관찰: '+esc(x.observation||'-')+'<br>입증취지: '+esc(x.purpose||'-')+'<br><span class="small">파일 SHA-256: '+esc(x.sha256||'-')+'</span>';
  document.getElementById('modal').classList.add('open');
  document.body.style.overflow='hidden';
}
function openPhoto(x){
  renderPhoto(x);
  if(!photoHistoryActive){
    history.pushState({evidencePhoto:true},'',location.href);
    photoHistoryActive=true;
  }
}
function hidePhoto(){
  document.getElementById('modal').classList.remove('open');
  document.getElementById('mimg').src='';
  document.body.style.overflow='';
}
function closePhoto(){
  if(photoHistoryActive){ history.back(); }
  else { hidePhoto(); }
}
window.addEventListener('popstate',()=>{
  if(photoHistoryActive){
    photoHistoryActive=false;
    hidePhoto();
  }
});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.getElementById('modal').classList.contains('open'))closePhoto()});
</script></body></html>
