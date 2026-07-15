<?php
require_once __DIR__.'/../lib/app.php';
$pageTitle='관리자 설정';
include '_top.php';
$pdo=db();
$ok='';$err='';

function generate_cron_secret(): string { return bin2hex(random_bytes(32)); }
function current_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'example.com';
    $script = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '/admin/settings.php');
    $base = preg_replace('~/admin/settings\.php$~','',$script);
    return rtrim($scheme.'://'.$host.$base,'/');
}

try {
    if(setting('notification_cron_key','')==='') set_setting('notification_cron_key',generate_cron_secret());
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $action=$_POST['action']??'save';
        if($action==='regenerate_cron_key'){
            set_setting('notification_cron_key',generate_cron_secret());
            $ok='새 CRON 보안키를 생성했습니다. 기존 CRON URL은 더 이상 사용할 수 없습니다.';
        } else {
            foreach(['org_name','grant_method','mandatory_rate','daily_max_people','request_months_ahead','employee_auth_mode','allowance_monthly_hours','allowance_daily_hours','allowance_rounding','alimtalk_enabled','ppurio_account','ppurio_sender_profile','ppurio_sender_number','ppurio_token_url','ppurio_message_url','notification_dispatch_mode','notification_max_attempts'] as $k){
                set_setting($k,$_POST[$k]??'');
            }
            // 비워두면 기존 인증키를 유지합니다.
            if(trim((string)($_POST['ppurio_auth_key']??''))!=='') set_setting('ppurio_auth_key',trim($_POST['ppurio_auth_key']));
            $ok='설정을 저장했습니다.';
        }
    }
} catch(Throwable $e){$err=$e->getMessage();}

$cronKey=setting('notification_cron_key');
$cronUrl=current_base_url().'/notification_cron.php?key='.rawurlencode($cronKey);
$pendingCount=(int)$pdo->query("SELECT COUNT(*) FROM notification_queue WHERE status IN ('pending','retry')")->fetchColumn();
$failedCount=(int)$pdo->query("SELECT COUNT(*) FROM notification_queue WHERE status='failed'")->fetchColumn();
$lastCronAt=setting('notification_cron_last_run','실행 이력 없음');
$lastCronResult=setting('notification_cron_last_result','');
?>
<div class="page-title-row">
  <div><h1>관리자 설정</h1><p class="page-desc">조직 운영기준, 연차수당 계산기준, 비즈뿌리오와 자동발송을 설정합니다.</p></div>
</div>
<?php if($ok):?><div class="alert ok"><?=h($ok)?></div><?php endif?>
<?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?>

<form method="post" class="settings-erp-form">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="save">
<div class="settings-layout">
  <section class="card settings-card">
    <div class="panel-title"><div><h2>운영 기준</h2><p>조직별 휴가 운영정책을 설정합니다.</p></div></div>
    <div class="form-grid cols-2">
      <label class="span-2">조직명<input name="org_name" value="<?=h(setting('org_name'))?>"></label>
      <label>연차 부여방식<select name="grant_method"><option value="fiscal" <?=setting('grant_method')==='fiscal'?'selected':''?>>회계연도 기준</option><option value="hire" <?=setting('grant_method')==='hire'?'selected':''?>>입사일 기준</option></select></label>
      <label>직원 신청 인증<select name="employee_auth_mode"><option value="none" <?=setting('employee_auth_mode')==='none'?'selected':''?>>이름 선택</option><option value="phone4" <?=setting('employee_auth_mode','phone4')==='phone4'?'selected':''?>>휴대폰 뒤 4자리</option></select></label>
      <label>기본 의무사용비율(%)<input type="number" step="0.1" name="mandatory_rate" value="<?=h(setting('mandatory_rate','70'))?>"></label>
      <label>동일 날짜 최대 신청인원<input type="number" min="0" name="daily_max_people" value="<?=h(setting('daily_max_people','2'))?>"><small>0이면 인원 제한을 사용하지 않습니다.</small></label>
      <label>신청 가능 미래 개월수<input type="number" min="0" name="request_months_ahead" value="<?=h(setting('request_months_ahead','2'))?>"></label>
    </div>

    <div class="erp-divider"></div>
    <div class="panel-title compact"><div><h2>연차수당 기준</h2><p>직원별 월 통상임금에 적용되는 공통 계산기준입니다.</p></div></div>
    <div class="form-grid cols-3">
      <label>월 기준시간<input type="number" step=".01" name="allowance_monthly_hours" value="<?=h(setting('allowance_monthly_hours','209'))?>"></label>
      <label>1일 기준시간<input type="number" step=".01" name="allowance_daily_hours" value="<?=h(setting('allowance_daily_hours','8'))?>"></label>
      <label>금액 처리<select name="allowance_rounding"><option value="1" <?=setting('allowance_rounding','1')==='1'?'selected':''?>>원 단위 반올림</option><option value="10" <?=setting('allowance_rounding')==='10'?'selected':''?>>10원 미만 절사</option><option value="100" <?=setting('allowance_rounding')==='100'?'selected':''?>>100원 미만 절사</option></select></label>
    </div>
  </section>

  <section class="card settings-card ppurio-settings-card">
    <div class="panel-title"><div><h2>비즈뿌리오 기본 설정</h2><p>계정과 발신정보를 입력하면 업무 PHP에서는 공통 알림 모듈로 호출됩니다.</p></div></div>
    <div class="help-notice"><strong>어디서 확인하나요?</strong> 비즈뿌리오 관리자에서 계정·인증키·발신프로필(Sender Key)·발신번호를 확인하여 입력합니다.</div>
    <div class="form-grid cols-2 ppurio-grid">
      <label>알림톡 사용<select name="alimtalk_enabled"><option value="0">사용 안 함</option><option value="1" <?=setting('alimtalk_enabled')==='1'?'selected':''?>>사용</option></select></label>
      <label>발송 방식<select name="notification_dispatch_mode"><option value="immediate" <?=setting('notification_dispatch_mode','immediate')==='immediate'?'selected':''?>>즉시 발송</option><option value="queue" <?=setting('notification_dispatch_mode')==='queue'?'selected':''?>>대기열(CRON) 발송</option></select></label>
      <label>비즈뿌리오 계정<input name="ppurio_account" value="<?=h(setting('ppurio_account'))?>" autocomplete="off" placeholder="비즈뿌리오 로그인 계정"></label>
      <label>인증키<input type="password" name="ppurio_auth_key" value="" autocomplete="new-password" placeholder="변경할 때만 입력"><small>저장된 키는 보안상 다시 표시하지 않습니다. 비워두면 기존 키를 유지합니다.</small></label>
      <label>발신프로필 키(Sender Key)<input name="ppurio_sender_profile" value="<?=h(setting('ppurio_sender_profile'))?>" placeholder="카카오 채널 발신프로필 키"></label>
      <label>발신번호<input name="ppurio_sender_number" value="<?=h(setting('ppurio_sender_number'))?>" placeholder="숫자만 입력"></label>
      <label class="span-2">토큰 URL<input name="ppurio_token_url" value="<?=h(setting('ppurio_token_url','https://api.bizppurio.com/v1/token'))?>"></label>
      <label class="span-2">메시지 URL<input name="ppurio_message_url" value="<?=h(setting('ppurio_message_url','https://api.bizppurio.com/v3/message'))?>"></label>
      <label>실패 재시도 횟수<input type="number" min="1" max="10" name="notification_max_attempts" value="<?=h(setting('notification_max_attempts','3'))?>"></label>
    </div>
    <p class="muted">업무별 템플릿 코드와 승인 문구는 <a class="text-link" href="notifications.php">알림톡 관리</a>에서 등록합니다.</p>
  </section>
</div>
<div class="sticky-save-bar"><button class="btn">전체 설정 저장</button></div>
</form>

<section class="card cron-manager-card">
  <div class="panel-title">
    <div><h2>알림톡 자동발송(CRON)</h2><p>대기열 방식 사용 시 카페24 또는 서버 스케줄러가 아래 URL을 정기적으로 호출해야 합니다.</p></div>
    <span class="status-pill <?=$pendingCount?'warning':'success'?>">대기 <?=$pendingCount?>건</span>
  </div>
  <div class="cron-stats">
    <div><span>마지막 실행</span><strong><?=h($lastCronAt)?></strong></div>
    <div><span>대기 건수</span><strong><?=number_format($pendingCount)?>건</strong></div>
    <div><span>실패 건수</span><strong><?=number_format($failedCount)?>건</strong></div>
    <div><span>최근 결과</span><strong><?=h($lastCronResult?:'-')?></strong></div>
  </div>
  <div class="cron-fields">
    <label>CRON 보안키 <button type="button" class="help-dot" title="외부에서 notification_cron.php를 무단 실행하지 못하도록 확인하는 시스템 내부 비밀키입니다. 비즈뿌리오에서 받는 키가 아닙니다.">?</button>
      <div class="copy-field"><input id="cronKey" value="<?=h($cronKey)?>" readonly><button type="button" class="btn sm ghost" data-copy="cronKey">복사</button></div>
      <small>설치 시 자동 생성된 내부 보안키입니다. 새 키 생성 시 기존 스케줄러 URL도 반드시 변경하세요.</small>
    </label>
    <label>CRON 실행 URL
      <div class="copy-field"><input id="cronUrl" value="<?=h($cronUrl)?>" readonly><button type="button" class="btn sm ghost" data-copy="cronUrl">복사</button></div>
      <small>카페24 CRON 또는 외부 스케줄러에 이 URL을 등록합니다. 권장 주기: 1~5분.</small>
    </label>
  </div>
  <div class="cron-actions">
    <form method="post" onsubmit="return confirm('새 키를 생성하면 기존 CRON URL이 즉시 무효화됩니다. 계속할까요?')">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="regenerate_cron_key"><button class="btn gray" type="submit">새 보안키 생성</button>
    </form>
    <a class="btn ghost" href="<?=h($cronUrl.'&limit=30')?>" target="_blank" rel="noopener">지금 실행 테스트</a>
  </div>
</section>
<script>
document.querySelectorAll('[data-copy]').forEach(function(btn){
  btn.addEventListener('click',async function(){
    var el=document.getElementById(btn.dataset.copy); if(!el)return;
    try{await navigator.clipboard.writeText(el.value);btn.textContent='복사됨';setTimeout(function(){btn.textContent='복사'},1300)}
    catch(e){el.select();document.execCommand('copy');btn.textContent='복사됨';setTimeout(function(){btn.textContent='복사'},1300)}
  });
});
</script>
<?php include '_bottom.php';?>
