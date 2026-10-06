<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/lib/common.php';
require_once dirname(__DIR__).'/lib/ppurio.php';
tp_admin_required();

$defaults=[
 'phone'=>'','eta'=>'2026-10-10 09:00','vessel_name'=>'GLOVIS CHORUS','vehicle_count'=>'2997',
 'model_guess'=>'TESLA 모델Y L','origin_port'=>'Shanghai (CNSHA)'
];
$v=$defaults; $result=null; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 foreach($v as $k=>$d) $v[$k]=trim((string)($_POST[$k]??$d));
 $phone=preg_replace('/\D/','',$v['phone']);
 if(!preg_match('/^01\d{8,9}$/',$phone)) $error='수신번호를 확인해 주세요.';
 elseif(!defined('PPURIO_ACCOUNT')||!PPURIO_ACCOUNT||!defined('PPURIO_AUTH_KEY')||!PPURIO_AUTH_KEY||!defined('PPURIO_SENDER_PROFILE')||!PPURIO_SENDER_PROFILE||!defined('PPURIO_TEMPLATE_TESLA_PORT')||!PPURIO_TEMPLATE_TESLA_PORT) $error='뿌리오 설정값이 config.php에 모두 등록되어 있는지 확인해 주세요.';
 else {
  try{
   $result=tp_send_alimtalk($phone,[
    'eta'=>$v['eta'],'vessel_name'=>$v['vessel_name'],'vehicle_count'=>(int)$v['vehicle_count'],
    'model_guess'=>$v['model_guess'],'origin_port'=>$v['origin_port']
   ],true); // 테스트 페이지에서만 PPURIO_ENABLED를 우회. 운영 크론에는 영향 없음.
  }catch(Throwable $e){$error=$e->getMessage();}
 }
}
function pretty_raw($raw){$j=json_decode((string)$raw,true);return $j?json_encode($j,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT):(string)$raw;}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>알림톡 안전 테스트</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f6f8}.card{border:0;border-radius:18px}pre{white-space:pre-wrap;word-break:break-all}</style></head><body><div class="container py-4" style="max-width:900px"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="fw-bold mb-1">📨 Tesla 알림톡 안전 테스트</h2><div class="text-secondary">v1.9.3.4 · 기본형 텍스트 알림톡 ALT 적용 · 운영 DB/notified_at 미변경</div></div><a class="btn btn-outline-dark" href="index.php">관리자</a></div>
<div class="alert alert-warning"><b>실제 1건 발송 테스트입니다.</b> 이 페이지에서만 <code>PPURIO_ENABLED=false</code>를 우회합니다. 운영 크론의 알림톡 설정은 그대로 OFF이며, Tesla 입항 DB·<code>notified_at</code>·수신자 DB는 수정하지 않습니다.</div>
<?php if($error):?><div class="alert alert-danger"><?=h($error)?></div><?php endif?>
<?php if($result):?><div class="alert <?=($result['http']??0)>=200&&($result['http']??0)<300?'alert-success':'alert-danger'?>"><b>발송 요청 완료</b> · HTTP <?=h($result['http']??'-')?> · <?=date('Y-m-d H:i:s')?></div><div class="card shadow-sm mb-3"><div class="card-body"><h6>뿌리오 응답</h6><pre class="mb-0"><?=h(pretty_raw($result['raw']??''))?></pre></div></div><?php endif?>
<div class="card shadow-sm"><div class="card-body"><form method="post" onsubmit="return confirm('입력한 번호로 실제 알림톡 1건을 발송합니다. 계속할까요?')"><div class="mb-3"><label class="form-label fw-bold">수신번호</label><input class="form-control" name="phone" value="<?=h($v['phone'])?>" placeholder="01012345678" required></div><div class="row g-3"><div class="col-md-6"><label class="form-label">입항일시 (var1)</label><input class="form-control" name="eta" value="<?=h($v['eta'])?>" required></div><div class="col-md-6"><label class="form-label">선박명 (var2)</label><input class="form-control" name="vessel_name" value="<?=h($v['vessel_name'])?>" required></div><div class="col-md-6"><label class="form-label">차량수량 (var3)</label><input class="form-control" type="number" min="1" name="vehicle_count" value="<?=h($v['vehicle_count'])?>" required></div><div class="col-md-6"><label class="form-label">예상모델 (var4)</label><input class="form-control" name="model_guess" value="<?=h($v['model_guess'])?>" required></div><div class="col-12"><label class="form-label">전출항지 (var5)</label><input class="form-control" name="origin_port" value="<?=h($v['origin_port'])?>" required></div></div><button class="btn btn-danger w-100 mt-4 py-3 fw-bold">실제 알림톡 테스트 1건 발송</button></form></div></div>
<div class="small text-secondary mt-3">※ 테스트 화면에는 인증키·토큰·senderProfile 값을 출력하지 않습니다. HTTP 2xx는 뿌리오가 요청을 접수했다는 의미이며 최종 카카오 수신 여부도 함께 확인해 주세요.</div></div></body></html>
