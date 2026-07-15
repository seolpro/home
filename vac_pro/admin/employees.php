<?php
require_once __DIR__.'/../lib/app.php';
$pageTitle='직원관리';
include '_top.php';
$pdo=db();
$msg='';
$year=(int)($_GET['year']??$_POST['salary_year']??date('Y'));

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $id=(int)($_POST['id']??0);
    $departmentId=($_POST['department_id']??'')!==''?(int)$_POST['department_id']:null;
    $quitDate=($_POST['quit_date']??'')!==''?$_POST['quit_date']:null;
    $customGrant=($_POST['custom_grant_days']??'')!==''?$_POST['custom_grant_days']:null;
    $mandatoryRate=($_POST['mandatory_rate']??'')!==''?$_POST['mandatory_rate']:null;
    $approvalLineId=($_POST['approval_line_id']??'')!==''?(int)$_POST['approval_line_id']:null;

    $data=[
        trim($_POST['employee_no']??''),
        trim($_POST['name']??''),
        trim($_POST['position']??''),
        $departmentId,
        trim($_POST['phone']??''),
        trim($_POST['email']??''),
        $_POST['hire_date']??date('Y-m-d'),
        $quitDate,
        $customGrant,
        $mandatoryRate,
        $approvalLineId,
        isset($_POST['allowance_enabled'])?1:0,
        isset($_POST['alimtalk_opt_in'])?1:0,
        (int)($_POST['sort_order']??0),
        isset($_POST['is_active'])?1:0,
        trim($_POST['memo']??'')
    ];

    if($id){
        $s=$pdo->prepare('UPDATE employees SET employee_no=?,name=?,position=?,department_id=?,phone=?,email=?,hire_date=?,quit_date=?,custom_grant_days=?,mandatory_rate=?,approval_line_id=?,allowance_enabled=?,alimtalk_opt_in=?,sort_order=?,is_active=?,memo=? WHERE id=?');
        $s->execute([...$data,$id]);
    }else{
        $s=$pdo->prepare('INSERT INTO employees(employee_no,name,position,department_id,phone,email,hire_date,quit_date,custom_grant_days,mandatory_rate,approval_line_id,allowance_enabled,alimtalk_opt_in,sort_order,is_active,memo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute($data);
        $id=(int)$pdo->lastInsertId();
    }

    if(($_POST['monthly_ordinary_wage']??'')!==''){
        $w=max(0,(int)str_replace(',','',$_POST['monthly_ordinary_wage']));
        $q=$pdo->prepare('INSERT INTO employee_salary_history(employee_id,apply_year,monthly_ordinary_wage,memo) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE monthly_ordinary_wage=VALUES(monthly_ordinary_wage),memo=VALUES(memo)');
        $q->execute([$id,(int)$_POST['salary_year'],$w,trim($_POST['salary_memo']??'')]);
    }
    $msg='저장되었습니다.';
}

$edit=null;
$salaryHistory=[];
$currentSalary=null;
if(isset($_GET['edit'])){
    $s=$pdo->prepare('SELECT * FROM employees WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit=$s->fetch();
    if($edit){
        $q=$pdo->prepare('SELECT * FROM employee_salary_history WHERE employee_id=? ORDER BY apply_year DESC');
        $q->execute([$edit['id']]);
        $salaryHistory=$q->fetchAll();
        foreach($salaryHistory as $salaryRow){
            if((int)$salaryRow['apply_year']===$year){
                $currentSalary=$salaryRow;
                break;
            }
        }
    }
}

$deps=$pdo->query('SELECT * FROM departments WHERE is_active=1 ORDER BY sort_order,name')->fetchAll();
$approvalLines=$pdo->query("SELECT al.id,al.name,d.name department_name FROM approval_lines al LEFT JOIN departments d ON d.id=al.department_id WHERE al.is_active=1 ORDER BY al.sort_order,al.id")->fetchAll();
$q=$pdo->prepare('SELECT e.*,d.name department_name,sh.monthly_ordinary_wage FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN employee_salary_history sh ON sh.employee_id=e.id AND sh.apply_year=? ORDER BY e.sort_order,e.id');
$q->execute([$year]);
$rows=$q->fetchAll();
?>

<div class="page-title-row">
    <div>
        <h1>직원관리</h1>
        <p class="page-desc">직원 기본정보, 연차 기준, 통상임금과 알림 수신 상태를 통합 관리합니다.</p>
    </div>
</div>

<?php if($msg):?><div class="alert ok"><?=$msg?></div><?php endif?>

<div class="employee-layout">
    <section class="employee-editor card">
        <div class="panel-title">
            <div>
                <h2><?=$edit?'직원 정보 수정':'신규 직원 등록'?></h2>
                <p><?=$edit?'선택한 직원의 인사 및 연차 기준정보를 수정합니다.':'직원 등록에 필요한 정보를 항목별로 입력합니다.'?></p>
            </div>
            <?php if($edit):?><a class="btn sm gray" href="employees.php?year=<?=$year?>">신규 등록</a><?php endif?>
        </div>

        <form method="post" class="erp-form">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="id" value="<?=h($edit['id']??'')?>">

            <div class="erp-section">
                <div class="erp-section-head">
                    <span class="erp-step">01</span>
                    <div><h3>기본 인적정보</h3><p>직원 식별 및 연락처 정보를 입력합니다.</p></div>
                </div>
                <div class="form-grid cols-2">
                    <label>사번<input name="employee_no" value="<?=h($edit['employee_no']??'')?>" placeholder="예: 2026001"></label>
                    <label>성명 <span class="required">필수</span><input name="name" required value="<?=h($edit['name']??'')?>" placeholder="직원 성명"></label>
                    <label>부서<select name="department_id"><option value="">미지정</option><?php foreach($deps as $d):?><option value="<?=$d['id']?>" <?=($edit['department_id']??'')==$d['id']?'selected':''?>><?=h($d['name'])?></option><?php endforeach?></select></label>
                    <label>직책<input name="position" value="<?=h($edit['position']??'')?>" placeholder="예: 과장"></label>
                    <label>휴대폰<input name="phone" value="<?=h($edit['phone']??'')?>" placeholder="010-0000-0000"></label>
                    <label>이메일<input type="email" name="email" value="<?=h($edit['email']??'')?>" placeholder="name@example.com"></label>
                </div>
            </div>

            <div class="erp-section">
                <div class="erp-section-head">
                    <span class="erp-step">02</span>
                    <div><h3>근무 및 연차 설정</h3><p>재직기간과 직원별 예외 연차기준을 설정합니다.</p></div>
                </div>
                <div class="form-grid cols-2">
                    <label>입사일 <span class="required">필수</span><input type="date" name="hire_date" required value="<?=h($edit['hire_date']??date('Y-m-d'))?>"></label>
                    <label>퇴사일<input type="date" name="quit_date" value="<?=h($edit['quit_date']??'')?>"><small>재직 중이면 비워두세요.</small></label>
                    <label>개별 부여일수<input type="number" step=".25" min="0" name="custom_grant_days" value="<?=h($edit['custom_grant_days']??'')?>" placeholder="조직 기본값 사용"><small>비워두면 관리자 기본설정을 적용합니다.</small></label>
                    <label>개별 의무사용률<input type="number" step=".1" min="0" max="100" name="mandatory_rate" value="<?=h($edit['mandatory_rate']??'')?>" placeholder="조직 기본값 사용"><small>퍼센트(%) 단위로 입력합니다.</small></label>
                    <label class="span-2">적용 결재선<select name="approval_line_id"><option value="">자동 적용 — 소속 부서 결재선 → 전체 기본 결재선</option><?php foreach($approvalLines as $line):?><option value="<?=$line['id']?>" <?=($edit['approval_line_id']??'')==$line['id']?'selected':''?>><?=h($line['name'].($line['department_name']?' · '.$line['department_name']:' · 전체'))?></option><?php endforeach?></select><small>직원별 예외 결재선이 필요한 경우에만 직접 선택합니다.</small></label>
                </div>
            </div>

            <div class="erp-section allowance-panel">
                <div class="erp-section-head">
                    <span class="erp-step">03</span>
                    <div><h3>연차수당 기초자료</h3><p>직원별 연도별 월 통상임금을 등록합니다.</p></div>
                </div>
                <div class="salary-grid">
                    <label class="salary-year">적용연도
                        <select name="salary_year">
                            <?php for($y=(int)date('Y')+1;$y>=(int)date('Y')-10;$y--):?>
                                <option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=$y?>년</option>
                            <?php endfor?>
                        </select>
                    </label>
                    <label class="salary-wage">월 통상임금
                        <div class="money-input"><input type="text" inputmode="numeric" name="monthly_ordinary_wage" value="<?=$currentSalary?number_format($currentSalary['monthly_ordinary_wage']):''?>" placeholder="예: 3,500,000"><span>원</span></div>
                    </label>
                    <label class="salary-memo">임금 메모<input name="salary_memo" value="<?=h($currentSalary['memo']??'')?>" placeholder="임금 적용기준 또는 참고사항"></label>
                </div>
                <label class="toggle-row"><input type="checkbox" name="allowance_enabled" <?=!isset($edit)||$edit['allowance_enabled']?'checked':''?>><span><strong>연차수당 계산 대상</strong><small>체크된 직원만 연차수당 계산 페이지에 포함됩니다.</small></span></label>
                <?php if($salaryHistory):?>
                    <div class="salary-history">
                        <div class="salary-history-title">연도별 통상임금 이력</div>
                        <div class="salary-history-list">
                            <?php foreach($salaryHistory as $sh):?><span><b><?=$sh['apply_year']?>년</b><?=number_format($sh['monthly_ordinary_wage'])?>원</span><?php endforeach?>
                        </div>
                    </div>
                <?php endif?>
            </div>

            <div class="erp-section">
                <div class="erp-section-head">
                    <span class="erp-step">04</span>
                    <div><h3>알림 및 운영 상태</h3><p>목록 순서와 서비스 사용 상태를 지정합니다.</p></div>
                </div>
                <div class="form-grid cols-2 compact-fields">
                    <label>표시순서<input type="number" name="sort_order" value="<?=h($edit['sort_order']??0)?>"><small>숫자가 작을수록 먼저 표시됩니다.</small></label>
                    <label>관리 메모<textarea name="memo" rows="3" placeholder="직원 관련 관리자 메모"><?=h($edit['memo']??'')?></textarea></label>
                </div>
                <div class="toggle-grid">
                    <label class="toggle-row"><input type="checkbox" name="alimtalk_opt_in" <?=!isset($edit)||$edit['alimtalk_opt_in']?'checked':''?>><span><strong>알림톡 수신</strong><small>신청 결과와 안내 메시지를 수신합니다.</small></span></label>
                    <label class="toggle-row"><input type="checkbox" name="is_active" <?=!isset($edit)||$edit['is_active']?'checked':''?>><span><strong>재직·활성 상태</strong><small>직원 선택목록과 주요 집계에 포함합니다.</small></span></label>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit"><?=$edit?'변경사항 저장':'직원 등록'?></button>
                <?php if($edit):?><a class="btn gray" href="employees.php?year=<?=$year?>">취소</a><?php endif?>
            </div>
        </form>
    </section>

    <section class="employee-list card">
        <div class="section-head employee-list-head">
            <div><h2>직원 목록</h2><p class="muted">총 <?=number_format(count($rows))?>명</p></div>
            <form method="get" class="year-filter"><label>통상임금 기준연도<select name="year" onchange="this.form.submit()"><?php for($y=(int)date('Y')+1;$y>=(int)date('Y')-5;$y--):?><option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=$y?>년</option><?php endfor?></select></label></form>
        </div>
        <div class="table-wrap">
            <table class="table employee-table">
                <thead><tr><th>순서</th><th>직원</th><th>부서·직책</th><th><?=$year?>년 월 통상임금</th><th>알림톡</th><th>상태</th><th>관리</th></tr></thead>
                <tbody>
                <?php if(!$rows):?><tr><td colspan="7" class="empty-cell">등록된 직원이 없습니다.</td></tr><?php endif?>
                <?php foreach($rows as $r):?><tr>
                    <td><span class="order-badge"><?=$r['sort_order']?></span></td>
                    <td><strong><?=h($r['name'])?></strong><div class="muted"><?=h($r['employee_no']?:'사번 미입력')?></div></td>
                    <td><?=h($r['department_name']?:'미지정')?><div class="muted"><?=h($r['position']?:'-')?></div></td>
                    <td class="wage-cell"><?=$r['monthly_ordinary_wage']!==null?'<strong>'.number_format($r['monthly_ordinary_wage']).'원</strong>':'<span class="status-pill warning">미입력</span>'?></td>
                    <td><span class="status-pill <?=$r['alimtalk_opt_in']?'success':'neutral'?>"><?=$r['alimtalk_opt_in']?'수신':'미수신'?></span></td>
                    <td><span class="status-pill <?=$r['is_active']?'success':'neutral'?>"><?=$r['is_active']?'활성':'비활성'?></span></td>
                    <td><a class="btn sm gray" href="?edit=<?=$r['id']?>&year=<?=$year?>">수정</a></td>
                </tr><?php endforeach?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.querySelectorAll('input[name="monthly_ordinary_wage"]').forEach(function(input){
    input.addEventListener('input', function(){
        var digits=this.value.replace(/[^0-9]/g,'');
        this.value=digits?Number(digits).toLocaleString('ko-KR'):'';
    });
});
</script>
<?php include '_bottom.php';?>
