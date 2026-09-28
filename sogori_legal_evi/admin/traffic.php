<?php
declare(strict_types=1);require dirname(__DIR__).'/config.php';admin_required();require dirname(__DIR__).'/lib/traffic.php';
$testMsg='';$testOk=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['traffic_sms_test'])){
    if(session_status()!==PHP_SESSION_ACTIVE) session_start();
    $posted=(string)($_POST['csrf']??'');
    $saved=(string)($_SESSION['traffic_csrf']??'');
    if($saved==='' || !hash_equals($saved,$posted)){
        $testOk=false;$testMsg='요청 확인값이 올바르지 않습니다. 새로고침 후 다시 시도하세요.';
    } elseif(!defined('TRAFFIC_SMS_ENABLED') || !TRAFFIC_SMS_ENABLED){
        $testOk=false;$testMsg='TRAFFIC_SMS_ENABLED가 false입니다. config.php에서 true로 변경한 뒤 테스트하세요.';
    } else {
        $testLevel=(int)(TRAFFIC_ALERT_LEVELS[0]??70);$virtual=(int)round(TRAFFIC_LIMIT_BYTES*($testLevel/100));
        $msg="[소고리 증거자료 트래픽 테스트]

트래픽 {$testLevel}% 경고 문자발송 테스트입니다.

가상 사용량: ".number_format($virtual/1073741824,2)."GB / ".number_format(TRAFFIC_LIMIT_BYTES/1073741824,2)."GB
가상 사용률: {$testLevel}.0%

※ 실제 트래픽 사용량과 관계없는 테스트입니다.";
        try{
            $r=traffic_send_sms($msg);
            $testOk=!empty($r['ok']);
            $testMsg=$testOk?'테스트 문자 발송 요청이 정상 처리되었습니다. 휴대전화 수신 여부를 확인하세요.':('테스트 문자 발송 실패: '.(string)($r['message']??'알 수 없는 오류').' / HTTP '.(string)($r['http_code']??'-').' / API '.(string)($r['api_code']??'-'));
        }catch(Throwable $e){$testOk=false;$testMsg='테스트 문자 발송 오류: '.$e->getMessage();}
    }
}
$syncMsg='';$syncOk=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['traffic_sync'])){
    if(session_status()!==PHP_SESSION_ACTIVE) session_start();
    $posted=(string)($_POST['csrf']??'');$saved=(string)($_SESSION['traffic_csrf']??'');
    if($saved==='' || !hash_equals($saved,$posted)){$syncOk=false;$syncMsg='요청 확인값이 올바르지 않습니다.';}
    else{
        $gb=(float)($_POST['actual_gb']??-1);
        if($gb<0 || $gb>100){$syncOk=false;$syncMsg='현재 Cafe24 사용량(GB)을 올바르게 입력하세요.';}
        else try{$r=traffic_sync_today_bytes((int)round($gb*1073741824));$syncOk=true;$syncMsg='오늘 사용량을 '.number_format($gb,2).' GB로 동기화했습니다. 이후 자체 관측 트래픽이 계속 더해집니다.';}catch(Throwable $e){$syncOk=false;$syncMsg='동기화 실패: '.$e->getMessage();}
    }
}
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
if(empty($_SESSION['traffic_csrf'])) $_SESSION['traffic_csrf']=bin2hex(random_bytes(16));
$trafficCsrf=(string)$_SESSION['traffic_csrf'];
$error='';try{$days=max(1,min(90,(int)($_GET['days']??31)));$s=traffic_stats($days);$today=traffic_stats(1);$today['observed_bytes']=$today['bytes'];$today['bytes']=traffic_today_bytes();$sync=traffic_today_sync();$pct=TRAFFIC_LIMIT_BYTES>0?$today['bytes']/TRAFFIC_LIMIT_BYTES*100:0;$recent=traffic_recent(100);}catch(Throwable $e){$error=$e->getMessage();$days=31;$s=$today=['pv'=>0,'uv'=>0,'bytes'=>0,'devices'=>[],'browsers'=>[],'pages'=>[]];$pct=0;$recent=[];}
function fmtb(int $b):string{if($b>=1073741824)return number_format($b/1073741824,2).' GB';if($b>=1048576)return number_format($b/1048576,1).' MB';return number_format($b/1024,1).' KB';}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>방문·트래픽 통계</title><style>*{box-sizing:border-box}body{margin:0;background:#f4f6f9;color:#172033;font-family:system-ui,-apple-system,"Noto Sans KR",sans-serif}.w{max-width:1180px;margin:auto;padding:20px}.top,.card{background:#fff;border:1px solid #dde3ec;border-radius:15px;padding:18px;margin-bottom:14px}.top{display:flex;justify-content:space-between;gap:12px;align-items:center}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.k{background:#fff;border:1px solid #dde3ec;border-radius:14px;padding:16px}.k b{display:block;font-size:25px;margin-top:6px}.muted{color:#667085;font-size:13px}.bar{height:18px;background:#e9edf3;border-radius:99px;overflow:hidden}.bar i{display:block;height:100%;background:#1f5eff}.warn{color:#b42318;font-weight:800}.err{background:#fff0f0;color:#b42318;padding:12px;border-radius:10px}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:9px;border-bottom:1px solid #e6e9ee;text-align:left}th{background:#f7f8fa;position:sticky;top:0}.scroll{max-height:520px;overflow:auto}.cols{display:grid;grid-template-columns:1fr 1fr;gap:14px}a{color:#174ea6}@media(max-width:800px){.grid{grid-template-columns:1fr 1fr}.cols{grid-template-columns:1fr}.top{display:block}}</style></head><body><main class="w"><section class="top"><div><h1 style="margin:0">방문자 · 트래픽 통계</h1><div class="muted">MySQL 저장 · 브라우저 Resource Timing 기반 전송량 관측</div></div><div><a href="index.php">← 사진관리</a> · <a href="../index.php">사이트 보기</a></div></section><?php if($error):?><div class="err">DB 조회 실패: <?=h($error)?> · install_traffic.php 실행 여부를 확인하세요.</div><?php endif?><?php if($syncMsg!==''):?><div class="<?= $syncOk?'card':'err' ?>"><b><?= $syncOk?'Cafe24 사용량 동기화 완료':'동기화 실패' ?></b><div style="margin-top:6px"><?=h($syncMsg)?></div></div><?php endif?><section class="grid"><div class="k"><span class="muted">오늘 방문(PV)</span><b><?=number_format($today['pv'])?></b></div><div class="k"><span class="muted">오늘 고유 IP</span><b><?=number_format($today['uv'])?></b></div><div class="k"><span class="muted">오늘 관측 전송량</span><b><?=fmtb((int)$today['bytes'])?></b></div><div class="k"><span class="muted">1.6GB 대비</span><b class="<?=$pct>=(int)(TRAFFIC_ALERT_LEVELS[0]??70)?'warn':''?>"><?=number_format($pct,1)?>%</b></div></section><?php if($testMsg!==''):?><div class="<?= $testOk?'card':'err' ?>"><b><?= $testOk?'문자 테스트 성공':'문자 테스트 실패' ?></b><div style="margin-top:6px"><?=h($testMsg)?></div></div><?php endif?>
<section class="card"><h2>Cafe24 현재 트래픽 동기화</h2><p class="muted">Cafe24 사용량 모니터링 화면의 오늘 현재 사용량을 입력하세요. 입력 시점의 자체 관측값과 차이를 오늘의 보정값으로 저장하며, 이후 발생하는 관측 트래픽은 자동으로 계속 더해집니다. 보정값은 날짜가 바뀌면 자동으로 적용되지 않습니다.</p><form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap" onsubmit="return confirm('입력한 Cafe24 현재 사용량으로 오늘 기준값을 동기화할까요?');"><input type="hidden" name="csrf" value="<?=h($trafficCsrf)?>"><input type="number" name="actual_gb" min="0" max="100" step="0.01" required placeholder="예: 0.41" style="width:130px;padding:10px;border:1px solid #ccd3dd;border-radius:9px"><b>GB</b><button type="submit" name="traffic_sync" value="1" style="border:0;border-radius:10px;padding:11px 16px;background:#174ea6;color:#fff;font-weight:800;cursor:pointer">현재 사용량으로 동기화</button></form><?php if(!empty($sync)):?><p class="muted" style="margin-bottom:0">마지막 동기화: <?=h((string)$sync['synced_at'])?> · 동기화 당시 Cafe24 <?=fmtb((int)$sync['actual_bytes_at_sync'])?> / 자체관측 <?=fmtb((int)$sync['observed_bytes_at_sync'])?> · 보정 <?=((int)$sync['offset_bytes']>=0?'+':'').fmtb(abs((int)$sync['offset_bytes']))?></p><?php endif?></section><section class="card"><h2>문자 경고 테스트</h2><p class="muted">실제 트래픽 값은 변경하지 않고, 설정된 1차 경고 기준 도달 상황을 가정한 테스트 문자를 관리자 휴대전화로 1건 발송합니다. 실제 실제 경고 이력에는 영향을 주지 않습니다.</p><form method="post" onsubmit="return confirm('관리자 휴대전화로 트래픽 테스트 문자를 발송할까요?');"><input type="hidden" name="csrf" value="<?=h($trafficCsrf)?>"><button type="submit" name="traffic_sms_test" value="1" style="border:0;border-radius:10px;padding:11px 16px;background:#172033;color:#fff;font-weight:800;cursor:pointer">1차 경고문자 테스트 발송</button></form></section>
<section class="card"><h2>오늘 트래픽 사용률</h2><div class="bar"><i style="width:<?=min(100,$pct)?>%"></i></div><p><b><?=fmtb((int)$today['bytes'])?></b> / <?=fmtb(TRAFFIC_LIMIT_BYTES)?> · <b class="<?=$pct>=(int)(TRAFFIC_ALERT_LEVELS[0]??70)?'warn':''?>">사용률 <?=number_format($pct,1)?>%</b> · 1차 경고 <?=h((string)(TRAFFIC_ALERT_LEVELS[0]??70))?>% <?=fmtb((int)(TRAFFIC_LIMIT_BYTES*((TRAFFIC_ALERT_LEVELS[0]??70)/100)))?></p><p class="muted">Cafe24 계정 전체 실제 트래픽과는 차이가 날 수 있는 웹앱 자체 관측값입니다.</p></section><div class="cols"><section class="card"><h2>최근 <?=$days?>일 요약</h2><p>PV <b><?=number_format($s['pv'])?></b> · 고유 IP <b><?=number_format($s['uv'])?></b> · 관측 전송량 <b><?=fmtb((int)$s['bytes'])?></b></p><h3>디바이스</h3><?php foreach($s['devices'] as$k=>$v):?><div><?=h($k)?> <b><?=$v?></b></div><?php endforeach?><h3>브라우저</h3><?php foreach($s['browsers'] as$k=>$v):?><div><?=h($k)?> <b><?=$v?></b></div><?php endforeach?></section><section class="card"><h2>페이지별 PV</h2><?php foreach($s['pages'] as$k=>$v):?><div><?=h($k)?> <b><?=$v?></b></div><?php endforeach?><h3>기간</h3><a href="?days=7">7일</a> · <a href="?days=31">31일</a> · <a href="?days=90">90일</a></section></div><section class="card"><h2>최근 접속 100건</h2><div class="scroll"><table><thead><tr><th>일시</th><th>IP</th><th>디바이스</th><th>OS</th><th>브라우저</th><th>페이지</th><th>유입</th></tr></thead><tbody><?php foreach($recent as$r):?><tr><td><?=h((string)($r['ts']??''))?></td><td><?=h((string)($r['ip']??''))?></td><td><?=h((string)($r['device']??''))?></td><td><?=h((string)($r['os']??''))?></td><td><?=h((string)($r['browser']??''))?></td><td><?=h((string)($r['page']??''))?></td><td><?=h(mb_strimwidth((string)($r['referer']??''),0,50,'…'))?></td></tr><?php endforeach?></tbody></table></div></section></main></body></html>
