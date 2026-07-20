<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/evidence.php';

$pdo = db();
$msg = '';
$err = '';
$requestMonths = max(0, (int)setting('request_months_ahead', '2'));
$requestMaxDate = date('Y-m-d', strtotime('+' . $requestMonths . ' months'));
$requestPeriodLabel = $requestMonths . '개월';
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedEvidence = null;
    try {
        verify_csrf();
        $eid = (int)($_POST['employee_id'] ?? 0);
        $emp = get_employee($eid);
        $lt = leave_type((int)($_POST['leave_type_id'] ?? 0));
        if (!$emp || !$lt) throw new RuntimeException('직원 또는 휴가유형을 확인하세요.');
        if (setting('employee_auth_mode', 'phone4') === 'phone4' && substr(phone_clean((string)$emp['phone']), -4) !== trim((string)($_POST['phone4'] ?? ''))) {
            throw new RuntimeException('휴대폰 뒤 4자리가 일치하지 않습니다.');
        }

        $start = trim((string)($_POST['start_date'] ?? ''));
        $end = !empty($lt['allow_date_range']) ? trim((string)($_POST['end_date'] ?? '')) : $start;
        if ($start === '' || $end === '' || $end < $start) throw new RuntimeException('신청일자를 확인하세요.');
        if ($start < $today) throw new RuntimeException('오늘 이전 날짜는 신청할 수 없습니다.');
        if ($start > $requestMaxDate || $end > $requestMaxDate) {
            throw new RuntimeException('신청 가능 기간은 오늘부터 ' . $requestPeriodLabel . ' 이내입니다. 신청 종료일은 ' . date('Y년 m월 d일', strtotime($requestMaxDate)) . '까지 선택할 수 있습니다.');
        }

        daily_capacity_check($eid, $start, $end, (int)$lt['id']);
        $days = !empty($lt['allow_custom_days']) ? (float)($_POST['requested_days'] ?? 0) : (float)$lt['default_days'];
        $unit = max(0.01, (float)$lt['min_unit']);
        if ($days <= 0 || abs(($days / $unit) - round($days / $unit)) > 0.00001) throw new RuntimeException('사용일수 단위를 확인하세요.');
        $half = !empty($lt['require_half_option']) ? trim((string)($_POST['half_option'] ?? '')) : '';
        if (!empty($lt['require_half_option']) && !in_array($half, ['오전', '오후'], true)) throw new RuntimeException('오전/오후를 선택하세요.');

        $savedEvidence = save_evidence_upload($_FILES['evidence_file'] ?? [], !empty($lt['require_evidence']));
        $deduct = !empty($lt['deduct_enabled']) ? $days : 0;
        $status = !empty($lt['require_approval']) ? 'pending' : 'approved';
        $steps = !empty($lt['require_approval']) ? approval_steps_for_employee($emp) : [];
        if (!empty($lt['require_approval']) && !$steps) throw new RuntimeException('적용 가능한 결재선이 없습니다. 관리자에게 문의하세요.');
        $lineId = !empty($steps[0]['approval_line_id']) ? (int)$steps[0]['approval_line_id'] : null;

        $pdo->beginTransaction();
        $s = $pdo->prepare('INSERT INTO leave_requests(employee_id,leave_type_id,approval_line_id,start_date,end_date,requested_days,deduct_days,half_option,memo,evidence_path,evidence_name,evidence_mime,evidence_size,status,approved_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$eid, $lt['id'], $lineId, $start, $end, $days, $deduct, $half, trim((string)($_POST['memo'] ?? '')), $savedEvidence['path'] ?? null, $savedEvidence['name'] ?? null, $savedEvidence['mime'] ?? null, $savedEvidence['size'] ?? null, $status, $status === 'approved' ? date('Y-m-d H:i:s') : null]);
        $rid = (int)$pdo->lastInsertId();

        if (!empty($lt['require_approval'])) {
            foreach ($steps as $i => $st) {
                $pdo->prepare('INSERT INTO request_approvals(request_id,step_order,approver_employee_id,status) VALUES(?,?,?,?)')->execute([$rid, $st['step_order'], $st['approver_employee_id'], $i === 0 ? 'pending' : 'waiting']);
            }
            $pdo->prepare("UPDATE leave_requests SET status='in_approval',current_step=1 WHERE id=?")->execute([$rid]);
        }
        $pdo->commit();

        if (!empty($lt['require_approval']) && !empty($steps[0]) && !empty($steps[0]['alimtalk_opt_in'])) {
            send_alimtalk('request', 'employee', (int)$steps[0]['approver_employee_id'], (string)$steps[0]['phone'], [
                'var1' => trim((string)$emp['name'] . ' ' . (string)$emp['position']), 'var2' => $lt['name'], 'var3' => $start,
                'var4' => $end, 'var5' => fmt_days($days), 'var6' => $half ?: '-'
            ]);
        }
        if (empty($lt['require_approval']) && !empty($emp['alimtalk_opt_in'])) {
            send_alimtalk('approved', 'employee', $eid, (string)$emp['phone'], [
                'var1' => $emp['name'], 'var2' => $start, 'var3' => $end, 'var4' => fmt_days($days), 'var5' => $lt['name']
            ]);
        }
        $msg = '신청이 정상 접수되었습니다.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        remove_evidence_file($savedEvidence);
        $err = $e->getMessage();
    }
}

$employees = $pdo->query("SELECT e.*,d.name department_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.is_active=1 ORDER BY e.sort_order,e.id")->fetchAll();
$types = $pdo->query('SELECT * FROM leave_types WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();
$from = $today;
$to = $requestMaxDate;
$s = $pdo->prepare("SELECT lr.*,e.name,e.position,lt.name leave_name,lt.color FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.status IN ('in_approval','approved') AND lr.end_date>=? AND lr.start_date<=? ORDER BY lr.start_date,e.sort_order");
$s->execute([$from, $to]);
$by = [];
foreach ($s->fetchAll() as $r) foreach (date_range(max($from, $r['start_date']), min($to, $r['end_date'])) as $d) $by[$d][] = $r;
ksort($by);
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/style.css?v=<?=filemtime(__DIR__.'/assets/style.css')?>">
<title><?=h(setting('org_name','휴가관리'))?></title>
</head>
<body>
<header class="top"><div class="wrap"><div class="brand"><?=h(setting('org_name','휴가관리시스템'))?></div><nav class="nav"><a href="my.php">내 휴가현황</a><a href="admin/login.php">관리자</a></nav></div></header>
<main class="wrap"><div class="grid">
<section class="col6 card">
<h1>휴가 사용신청</h1><p class="muted">신청 후 설정된 결재선에 따라 순차 승인됩니다.</p>
<div class="request-period-guide"><strong>신청 가능 기간</strong><span>신청 시작일 기준 오늘부터 <?=h($requestPeriodLabel)?> 이내</span><small><?=h(date('Y년 m월 d일',strtotime($requestMaxDate)))?>까지 신청할 수 있습니다.</small></div>
<?php if($msg):?><div class="alert ok"><?=h($msg)?></div><?php endif?><?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?>
<form method="post" enctype="multipart/form-data" id="leave_request_form">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<label>👤 신청직원<select name="employee_id" id="employee_id" required><option value="">선택하세요</option><?php foreach($employees as $e):?><option value="<?=$e['id']?>"><?=h($e['name'].' '.$e['position'].' · '.$e['department_name'])?></option><?php endforeach?></select></label>

<div id="employee_leave_summary" class="employee-summary" hidden>
<div class="employee-summary-head"><div><strong id="summary_employee_name">개인별 연차현황</strong><small id="summary_year"></small></div><span id="summary_mandatory_badge" class="status-pill neutral">조회 중</span></div>
<div class="employee-summary-grid">
<div><span>부여연차</span><strong id="summary_granted">-</strong></div><div><span>승인사용</span><strong id="summary_approved">-</strong></div><div><span>결재중</span><strong id="summary_pending">-</strong></div><div><span>잔여연차</span><strong id="summary_remaining">-</strong></div>
</div>
<div class="mandatory-summary"><div class="mandatory-summary-line"><span>의무사용 현황</span><strong><span id="summary_usage_rate">0</span>% / 목표 <span id="summary_mandatory_rate">0</span>%</strong></div><div class="progress"><span id="summary_progress" style="width:0%"></span></div><p id="summary_mandatory_text" class="muted"></p></div>
</div>

<?php if(setting('employee_auth_mode','phone4')==='phone4'):?><label>휴대폰 뒤 4자리<input name="phone4" maxlength="4" inputmode="numeric" required></label><?php endif?>
<label>휴가유형<select name="leave_type_id" id="leave_type" required><option value="">선택하세요</option><?php foreach($types as $t):?><option value="<?=$t['id']?>" data-days="<?=h($t['default_days'])?>" data-custom="<?=(int)$t['allow_custom_days']?>" data-half="<?=(int)$t['require_half_option']?>" data-range="<?=(int)$t['allow_date_range']?>" data-evidence="<?=(int)$t['require_evidence']?>"><?=h($t['name'])?></option><?php endforeach?></select></label>
<div class="grid"><label class="col6">시작일<input type="date" name="start_date" min="<?=h($today)?>" max="<?=h($requestMaxDate)?>" required><small>선택 가능: 오늘 ~ <?=h($requestMaxDate)?></small></label><label class="col6">종료일<input type="date" name="end_date" min="<?=h($today)?>" max="<?=h($requestMaxDate)?>" required></label><label class="col6">사용일수<input type="number" step=".25" name="requested_days" value="1" required></label><label class="col6">오전/오후<select name="half_option"><option value="">해당없음</option><option>오전</option><option>오후</option></select></label></div>
<div id="evidence_field" class="evidence-upload-box" hidden><label>증빙서류 첨부 <span class="required">필수</span><input type="file" name="evidence_file" id="evidence_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif"></label><p>PDF, JPG, PNG, WEBP, HEIC · 최대 <?=number_format(evidence_max_bytes()/1024/1024)?>MB</p><div id="evidence_file_name" class="evidence-file-name"></div></div>
<label>참고사항<textarea name="memo" placeholder="사유 또는 참고사항"></textarea></label><button class="btn">✅ 신청 제출</button>
</form>
</section>
<section class="col6"><div class="card"><h2>일자별 신청현황</h2><p class="muted">오늘부터 향후 <?=h($requestPeriodLabel)?>의 승인대기·승인 현황입니다.</p><?php if(!$by):?><p>등록된 일정이 없습니다.</p><?php endif?><?php foreach($by as $d=>$items):?><div class="calendar-day"><strong><?=h($d)?> (<?=['일','월','화','수','목','금','토'][date('w',strtotime($d))]?>)</strong><?php foreach($items as $r):?><div><span style="color:<?=h($r['color'])?>">●</span> <?=h($r['name'].' '.$r['position'])?> · <?=h($r['leave_name'])?> <?=h($r['half_option'])?> <span class="badge"><?=h($r['status']==='approved'?'승인':'결재중')?></span></div><?php endforeach?></div><?php endforeach?></div></section>
</div></main>
<script>
const leaveType=document.querySelector('#leave_type');
const employeeSelect=document.querySelector('#employee_id');
const endDate=document.querySelector('[name=end_date]');
const startDate=document.querySelector('[name=start_date]');
const requestedDays=document.querySelector('[name=requested_days]');
const halfOption=document.querySelector('[name=half_option]');
const evidenceField=document.querySelector('#evidence_field');
const evidenceFile=document.querySelector('#evidence_file');
const evidenceFileName=document.querySelector('#evidence_file_name');
const summaryBox=document.querySelector('#employee_leave_summary');

function fmtDays(value){const n=Number(value||0);return Number.isInteger(n)?String(n):String(Math.round(n*100)/100)}
async function loadEmployeeSummary(){
 const employeeId=employeeSelect.value;
 if(!employeeId){summaryBox.hidden=true;return;}
 summaryBox.hidden=false;document.querySelector('#summary_employee_name').textContent='개인별 연차현황';document.querySelector('#summary_mandatory_badge').textContent='조회 중';
 try{
  const response=await fetch('api/employee_leave_summary.php?employee_id='+encodeURIComponent(employeeId)+'&year=<?=date('Y')?>',{credentials:'same-origin',cache:'no-store'});
  const data=await response.json();if(!data.ok)throw new Error(data.message||'현황을 불러오지 못했습니다.');
  document.querySelector('#summary_employee_name').textContent=data.employee_name;
  document.querySelector('#summary_year').textContent=data.year+'년 기준';
  document.querySelector('#summary_granted').textContent=fmtDays(data.granted)+'일';
  document.querySelector('#summary_approved').textContent=fmtDays(data.approved)+'일';
  document.querySelector('#summary_pending').textContent=fmtDays(data.pending)+'일';
  document.querySelector('#summary_remaining').textContent=fmtDays(data.remaining)+'일';
  document.querySelector('#summary_usage_rate').textContent=data.usage_rate;
  document.querySelector('#summary_mandatory_rate').textContent=fmtDays(data.mandatory_rate);
  document.querySelector('#summary_progress').style.width=Math.min(100,Number(data.usage_rate||0))+'%';
  const badge=document.querySelector('#summary_mandatory_badge');badge.textContent=data.mandatory_achieved?'의무사용 달성':'의무사용 진행 중';badge.className='status-pill '+(data.mandatory_achieved?'success':'warning');
  document.querySelector('#summary_mandatory_text').textContent=data.mandatory_achieved?'목표 의무사용일수 '+fmtDays(data.mandatory_days)+'일을 달성했습니다.':'목표 '+fmtDays(data.mandatory_days)+'일까지 '+fmtDays(data.mandatory_remaining)+'일 더 사용해야 합니다.';
 }catch(error){document.querySelector('#summary_mandatory_badge').textContent='조회 실패';document.querySelector('#summary_mandatory_text').textContent=error.message;}
}
function updateLeaveTypeFields(){
 const o=leaveType.selectedOptions[0];if(!o||!o.value){evidenceField.hidden=true;evidenceFile.required=false;return;}
 requestedDays.value=o.dataset.days||1;requestedDays.readOnly=o.dataset.custom==='0';
 const useRange=o.dataset.range!=='0';endDate.disabled=!useRange;endDate.required=useRange;if(!useRange)endDate.value=startDate.value;
 const useHalf=o.dataset.half!=='0';halfOption.disabled=!useHalf;if(!useHalf)halfOption.value='';
 const needsEvidence=o.dataset.evidence==='1';evidenceField.hidden=!needsEvidence;evidenceFile.required=needsEvidence;if(!needsEvidence){evidenceFile.value='';evidenceFileName.textContent='';}
}
employeeSelect.addEventListener('change',loadEmployeeSummary);leaveType.addEventListener('change',updateLeaveTypeFields);
startDate.addEventListener('change',()=>{endDate.min=startDate.value||'<?=h($today)?>';if(leaveType.selectedOptions[0]?.dataset.range==='0')endDate.value=startDate.value;});
evidenceFile.addEventListener('change',()=>{evidenceFileName.textContent=evidenceFile.files?.[0]?.name?'선택된 파일: '+evidenceFile.files[0].name:'';});
updateLeaveTypeFields();
</script>
</body></html>
