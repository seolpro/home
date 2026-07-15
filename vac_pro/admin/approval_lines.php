<?php
require_once __DIR__.'/../lib/app.php';
$pageTitle='결재선 관리';
include '_top.php';
$pdo=db();
$msg=''; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action'] ?? 'save';
    try {
        if ($action==='delete') {
            $id=(int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM approval_lines WHERE id=?')->execute([$id]);
            $msg='결재선이 삭제되었습니다.';
        } else {
            $id=(int)($_POST['id'] ?? 0);
            $name=trim($_POST['name'] ?? '');
            $departmentId=($_POST['department_id'] ?? '')!=='' ? (int)$_POST['department_id'] : null;
            $sortOrder=(int)($_POST['sort_order'] ?? 0);
            $isActive=isset($_POST['is_active']) ? 1 : 0;
            $approvers=array_values(array_filter(array_map('intval', $_POST['approvers'] ?? [])));
            if ($name==='') throw new RuntimeException('결재선명을 입력하세요.');
            if (!$approvers) throw new RuntimeException('결재자를 1명 이상 선택하세요.');
            if (count($approvers)!==count(array_unique($approvers))) throw new RuntimeException('동일한 직원을 여러 단계에 중복 지정할 수 없습니다.');

            $pdo->beginTransaction();
            if ($id>0) {
                $s=$pdo->prepare('UPDATE approval_lines SET name=?,department_id=?,sort_order=?,is_active=? WHERE id=?');
                $s->execute([$name,$departmentId,$sortOrder,$isActive,$id]);
                $pdo->prepare('DELETE FROM approval_line_steps WHERE line_id=?')->execute([$id]);
                $lineId=$id;
            } else {
                $s=$pdo->prepare('INSERT INTO approval_lines(name,department_id,sort_order,is_active) VALUES(?,?,?,?)');
                $s->execute([$name,$departmentId,$sortOrder,$isActive]);
                $lineId=(int)$pdo->lastInsertId();
            }
            $ins=$pdo->prepare('INSERT INTO approval_line_steps(line_id,step_order,approver_employee_id) VALUES(?,?,?)');
            foreach ($approvers as $idx=>$employeeId) $ins->execute([$lineId,$idx+1,$employeeId]);
            $pdo->commit();
            $msg=$id>0?'결재선이 수정되었습니다.':'결재선이 추가되었습니다.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err=$e->getMessage();
    }
}

$editId=(int)($_GET['edit'] ?? 0);
$editLine=null; $editSteps=[];
if ($editId>0) {
    $s=$pdo->prepare('SELECT * FROM approval_lines WHERE id=?');$s->execute([$editId]);$editLine=$s->fetch();
    $s=$pdo->prepare('SELECT approver_employee_id FROM approval_line_steps WHERE line_id=? ORDER BY step_order');$s->execute([$editId]);$editSteps=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
}
$deps=$pdo->query('SELECT * FROM departments WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();
$employees=$pdo->query("SELECT e.id,e.name,e.position,e.phone,e.alimtalk_opt_in,e.sort_order,d.name department_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.is_active=1 ORDER BY e.sort_order,e.id")->fetchAll();
$lines=$pdo->query("SELECT al.*,d.name department_name,GROUP_CONCAT(CONCAT(als.step_order,'단계 ',e.name,IF(e.position IS NULL OR e.position='','',CONCAT(' ',e.position))) ORDER BY als.step_order SEPARATOR ' → ') steps, SUM(CASE WHEN e.phone IS NULL OR e.phone='' THEN 1 ELSE 0 END) missing_phones FROM approval_lines al LEFT JOIN departments d ON d.id=al.department_id LEFT JOIN approval_line_steps als ON als.line_id=al.id LEFT JOIN employees e ON e.id=als.approver_employee_id GROUP BY al.id ORDER BY al.sort_order,al.id")->fetchAll();
function phone_masked($v){$n=preg_replace('/\D+/','',(string)$v);if(strlen($n)>=10)return substr($n,0,3).'-****-'.substr($n,-4);return $n?:'미등록';}
?>
<div class="page-title-row"><div><h1>결재선 관리</h1><p class="page-desc">직원관리의 재직 직원을 불러와 부서별·공통 결재선을 구성합니다.</p></div></div>
<?php if($msg):?><div class="alert ok"><?=h($msg)?></div><?php endif?>
<?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?>
<div class="approval-layout">
<section class="card approval-editor">
  <div class="panel-title"><div><h2><?=$editLine?'결재선 수정':'새 결재선 등록'?></h2><p>결재자는 직원관리의 휴대폰·부서·직책 정보를 그대로 사용합니다.</p></div><span class="erp-step">01</span></div>
  <form method="post" class="erp-form" id="approvalForm">
    <input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$editLine['id']??0?>"><input type="hidden" name="action" value="save">
    <div class="erp-section">
      <div class="erp-section-head"><span class="erp-step">01</span><div><h3>기본정보</h3><p>부서를 지정하지 않으면 전체 직원의 기본 결재선으로 사용합니다.</p></div></div>
      <div class="form-grid cols-2">
        <label>결재선명 <span class="required">필수</span><input name="name" required value="<?=h($editLine['name']??'')?>" placeholder="예: 본점 기본 결재선"></label>
        <label>적용 부서<select name="department_id"><option value="">전체 기본 결재선</option><?php foreach($deps as $d):?><option value="<?=$d['id']?>" <?=((string)($editLine['department_id']??'')===(string)$d['id'])?'selected':''?>><?=h($d['name'])?></option><?php endforeach?></select></label>
        <label>표시순서<input type="number" name="sort_order" value="<?=h($editLine['sort_order']??0)?>"></label>
        <label class="toggle-row approval-active"><input type="checkbox" name="is_active" value="1" <?=!$editLine||$editLine['is_active']?'checked':''?>><span><strong>사용 중</strong><small>해제하면 신규 신청에 적용되지 않습니다.</small></span></label>
      </div>
    </div>
    <div class="erp-section">
      <div class="erp-section-head"><span class="erp-step">02</span><div><h3>단계별 결재자</h3><p>1단계부터 순서대로 승인 요청 알림톡이 발송됩니다.</p></div></div>
      <div class="approval-step-list">
      <?php for($i=1;$i<=3;$i++):$selected=$editSteps[$i-1]??0;?>
        <div class="approval-step-row">
          <div class="approval-step-number"><strong><?=$i?></strong><span>단계</span></div>
          <label>결재자 선택<select name="approvers[]" class="approver-select" data-step="<?=$i?>"><option value="">사용 안 함</option><?php foreach($employees as $e):?><option value="<?=$e['id']?>" data-phone="<?=h(phone_masked($e['phone']))?>" data-phone-ok="<?=$e['phone']?1:0?>" data-optin="<?=$e['alimtalk_opt_in']?>" data-dept="<?=h($e['department_name']?:'부서 미지정')?>" data-position="<?=h($e['position']?:'직책 미지정')?>" <?=$selected===$e['id']?'selected':''?>><?=h($e['name'].' · '.($e['position']?:'직책 미지정').' · '.($e['department_name']?:'부서 미지정'))?></option><?php endforeach?></select></label>
          <div class="approver-preview" id="preview<?=$i?>"><span class="muted">직원을 선택하세요.</span></div>
        </div>
      <?php endfor?>
      </div>
      <?php if(!$employees):?><div class="alert err">재직 직원이 없습니다. 먼저 직원관리에서 직원을 등록하세요.</div><?php endif?>
    </div>
    <div class="form-actions"><button class="btn" <?=$employees?'':'disabled'?>><?=$editLine?'수정 저장':'결재선 등록'?></button><?php if($editLine):?><a class="btn gray" href="approval_lines.php">취소</a><?php endif?></div>
  </form>
</section>
<section class="card approval-list-card">
  <div class="section-head"><div><h2>등록된 결재선</h2><p class="muted">직원 휴대폰이 미등록된 단계는 알림톡을 발송할 수 없습니다.</p></div></div>
  <div class="table-wrap"><table class="table approval-table"><thead><tr><th>순서</th><th>명칭/적용범위</th><th>결재순서</th><th>상태</th><th>관리</th></tr></thead><tbody>
  <?php if(!$lines):?><tr><td colspan="5" class="empty-cell">등록된 결재선이 없습니다.</td></tr><?php endif?>
  <?php foreach($lines as $l):?><tr><td><span class="order-badge"><?=$l['sort_order']?></span></td><td><strong><?=h($l['name'])?></strong><div class="muted"><?=h($l['department_name']?:'전체 기본 결재선')?></div></td><td><?=h($l['steps']?:'단계 없음')?><?php if((int)$l['missing_phones']>0):?><div><span class="status-pill warning">휴대폰 미등록 <?=$l['missing_phones']?>명</span></div><?php endif?></td><td><span class="status-pill <?=$l['is_active']?'success':'neutral'?>"><?=$l['is_active']?'사용':'중지'?></span></td><td><div class="actions"><a class="btn sm ghost" href="?edit=<?=$l['id']?>">수정</a><form method="post" onsubmit="return confirm('이 결재선을 삭제하시겠습니까?');"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$l['id']?>"><button class="btn sm red" name="action" value="delete">삭제</button></form></div></td></tr><?php endforeach?>
  </tbody></table></div>
</section>
</div>
<script>
function refreshApprover(select){const o=select.selectedOptions[0],box=document.getElementById('preview'+select.dataset.step);if(!o||!o.value){box.innerHTML='<span class="muted">직원을 선택하세요.</span>';return;}const ok=o.dataset.phoneOk==='1',opt=o.dataset.optin==='1';box.innerHTML=`<strong>${o.textContent.split(' · ')[0]}</strong><span>${o.dataset.position} · ${o.dataset.dept}</span><span class="${ok?'phone-ok':'phone-missing'}">휴대폰 ${o.dataset.phone}</span><span class="status-pill ${opt?'success':'neutral'}">알림톡 ${opt?'수신':'미수신'}</span>`;}
document.querySelectorAll('.approver-select').forEach(s=>{s.addEventListener('change',()=>refreshApprover(s));refreshApprover(s)});
document.getElementById('approvalForm')?.addEventListener('submit',e=>{const vals=[...document.querySelectorAll('.approver-select')].map(s=>s.value).filter(Boolean);if(new Set(vals).size!==vals.length){e.preventDefault();alert('동일한 직원을 여러 단계에 중복 지정할 수 없습니다.');}});
</script>
<?php include '_bottom.php';?>
