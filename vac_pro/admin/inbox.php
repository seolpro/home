<?php
require_once __DIR__.'/../lib/app.php';
require_once __DIR__.'/../lib/notify.php';
require_once __DIR__.'/../lib/auth.php';
require_approver();

$pageTitle='내 결재함';
include '_top.php';
$pdo=db();
$msg='';
$err='';
$myEmployeeId=admin_employee_id();

if($myEmployeeId<1){
    $err='현재 로그인 계정이 직원정보와 연결되어 있지 않습니다. 권한계정 관리에서 연결직원을 지정하세요.';
}

if($_SERVER['REQUEST_METHOD']==='POST' && $myEmployeeId>0){
    verify_csrf();
    $id=(int)($_POST['id']??0);
    $action=(string)($_POST['action']??'');
    $comment=trim((string)($_POST['comment']??''));

    $pdo->beginTransaction();
    try{
        $s=$pdo->prepare('SELECT lr.*,e.phone,e.name employee_name,e.position,e.alimtalk_opt_in,lt.name leave_name
                          FROM leave_requests lr
                          JOIN employees e ON e.id=lr.employee_id
                          JOIN leave_types lt ON lt.id=lr.leave_type_id
                          WHERE lr.id=? FOR UPDATE');
        $s->execute([$id]);
        $r=$s->fetch(PDO::FETCH_ASSOC);
        if(!$r) throw new RuntimeException('신청을 찾을 수 없습니다.');
        if(!in_array($r['status'],['pending','in_approval'],true)) throw new RuntimeException('이미 처리된 신청입니다.');

        $a=$pdo->prepare("SELECT ra.*,ae.name approver_name,ae.position approver_position
                          FROM request_approvals ra
                          JOIN employees ae ON ae.id=ra.approver_employee_id
                          WHERE ra.request_id=?
                            AND ra.step_order=?
                            AND ra.status='pending'
                          LIMIT 1 FOR UPDATE");
        $a->execute([$id,(int)$r['current_step']]);
        $ap=$a->fetch(PDO::FETCH_ASSOC);
        if(!$ap) throw new RuntimeException('현재 결재차례를 찾을 수 없습니다.');
        if((int)$ap['approver_employee_id']!==$myEmployeeId){
            throw new RuntimeException('현재 로그인한 직원의 결재 차례가 아닙니다.');
        }

        $prev=$pdo->prepare("SELECT COUNT(*) FROM request_approvals
                            WHERE request_id=? AND step_order<? AND status<>'approved'");
        $prev->execute([$id,(int)$ap['step_order']]);
        if((int)$prev->fetchColumn()>0) throw new RuntimeException('이전 결재단계가 완료되지 않았습니다.');

        $nextApproval=null;
        if($action==='reject'){
            if($comment==='') throw new RuntimeException('반려 사유를 입력하세요.');
            $pdo->prepare("UPDATE request_approvals SET status='rejected',comment=?,acted_at=NOW() WHERE id=? AND status='pending'")
                ->execute([$comment,$ap['id']]);
            $pdo->prepare("UPDATE request_approvals SET status='skipped',comment='이전 단계 반려로 종료' WHERE request_id=? AND step_order>? AND status='waiting'")
                ->execute([$id,$ap['step_order']]);
            $pdo->prepare("UPDATE leave_requests SET status='rejected',reject_reason=?,current_step=? WHERE id=?")
                ->execute([$comment,$ap['step_order'],$id]);
            $event='rejected';
        }elseif($action==='approve'){
            $up=$pdo->prepare("UPDATE request_approvals SET status='approved',comment=?,acted_at=NOW() WHERE id=? AND status='pending'");
            $up->execute([$comment,$ap['id']]);
            if($up->rowCount()!==1) throw new RuntimeException('결재상태가 변경되었습니다. 화면을 새로고침하세요.');

            $next=$pdo->prepare("SELECT * FROM request_approvals
                                WHERE request_id=? AND step_order>? AND status='waiting'
                                ORDER BY step_order ASC LIMIT 1 FOR UPDATE");
            $next->execute([$id,$ap['step_order']]);
            $nextApproval=$next->fetch(PDO::FETCH_ASSOC);
            if($nextApproval){
                $pdo->prepare("UPDATE request_approvals SET status='pending' WHERE id=? AND status='waiting'")
                    ->execute([$nextApproval['id']]);
                $pdo->prepare("UPDATE leave_requests SET status='in_approval',current_step=? WHERE id=?")
                    ->execute([$nextApproval['step_order'],$id]);
                $event='next_approval';
            }else{
                $remaining=$pdo->prepare("SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND status<>'approved'");
                $remaining->execute([$id]);
                if((int)$remaining->fetchColumn()>0) throw new RuntimeException('미완료 결재단계가 남아 있어 최종승인할 수 없습니다.');
                $pdo->prepare("UPDATE leave_requests SET status='approved',approved_at=NOW(),current_step=? WHERE id=?")
                    ->execute([$ap['step_order'],$id]);
                $event='approved';
            }
        }else{
            throw new RuntimeException('올바르지 않은 처리 요청입니다.');
        }

        $pdo->commit();

        if(in_array($event,['approved','rejected'],true) && !empty($r['alimtalk_opt_in'])){
            send_alimtalk($event,'employee',$r['employee_id'],$r['phone'],[
                'var1'=>trim($r['employee_name'].' '.$r['position']),
                'var2'=>$r['leave_name'],
                'var3'=>$r['start_date'],
                'var4'=>$r['end_date'],
                'var5'=>fmt_days($r['requested_days']),
                'var6'=>$comment?:'-'
            ]);
        }elseif($event==='next_approval' && $nextApproval){
            $q=$pdo->prepare('SELECT name,position,phone,alimtalk_opt_in FROM employees WHERE id=?');
            $q->execute([$nextApproval['approver_employee_id']]);
            $na=$q->fetch(PDO::FETCH_ASSOC);
            if($na && !empty($na['alimtalk_opt_in'])){
                send_alimtalk('next_approval','employee',$nextApproval['approver_employee_id'],$na['phone'],[
                    'var1'=>trim($r['employee_name'].' '.$r['position']),
                    'var2'=>$r['leave_name'],
                    'var3'=>$r['start_date'],
                    'var4'=>$r['end_date'],
                    'var5'=>fmt_days($r['requested_days']),
                    'var6'=>trim($na['name'].' '.$na['position'])
                ]);
            }
        }
        $msg=$action==='approve' ? $ap['step_order'].'차 결재가 승인되었습니다.' : '신청이 반려되었습니다.';
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        $err=$e->getMessage();
    }
}

$rows=[];
$completed=[];
if($myEmployeeId>0){
    $sql="SELECT lr.*,e.name employee_name,e.position,d.name department_name,lt.name leave_name,
                 ra.id approval_id,ra.step_order,ra.status approval_status,
                 GROUP_CONCAT(CONCAT(allra.step_order,'차 ',COALESCE(ae.name,'미지정'),' ',COALESCE(ae.position,''),' [',allra.status,']') ORDER BY allra.step_order SEPARATOR ' → ') approval_state
          FROM request_approvals ra
          JOIN leave_requests lr ON lr.id=ra.request_id
          JOIN employees e ON e.id=lr.employee_id
          LEFT JOIN departments d ON d.id=e.department_id
          JOIN leave_types lt ON lt.id=lr.leave_type_id
          LEFT JOIN request_approvals allra ON allra.request_id=lr.id
          LEFT JOIN employees ae ON ae.id=allra.approver_employee_id
          WHERE ra.approver_employee_id=?
            AND ra.status='pending'
            AND ra.step_order=lr.current_step
            AND lr.status IN ('pending','in_approval')
          GROUP BY lr.id,ra.id,e.name,e.position,d.name,lt.name
          ORDER BY lr.created_at ASC,lr.id ASC";
    $s=$pdo->prepare($sql);
    $s->execute([$myEmployeeId]);
    $rows=$s->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare("SELECT lr.id,e.name employee_name,e.position,lt.name leave_name,lr.start_date,lr.end_date,lr.requested_days,ra.step_order,ra.status,ra.comment,ra.acted_at
                      FROM request_approvals ra
                      JOIN leave_requests lr ON lr.id=ra.request_id
                      JOIN employees e ON e.id=lr.employee_id
                      JOIN leave_types lt ON lt.id=lr.leave_type_id
                      WHERE ra.approver_employee_id=? AND ra.status IN ('approved','rejected')
                      ORDER BY ra.acted_at DESC LIMIT 30");
    $q->execute([$myEmployeeId]);
    $completed=$q->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="page-title-row"><div><h1>내 결재함</h1><p class="page-desc">현재 본인의 결재 차례인 신청만 표시됩니다. 승인하면 다음 단계 결재권자에게 자동으로 넘어갑니다.</p></div><div><span class="badge"><?=count($rows)?>건 대기</span></div></div>
<?php if($msg):?><div class="alert ok"><?=h($msg)?></div><?php endif?>
<?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?>

<div class="card">
<div class="card-head"><div><h2>결재 대기</h2><p class="muted">현재 처리 가능한 신청입니다.</p></div></div>
<div class="table-wrap"><table class="table"><tr><th>신청자</th><th>부서</th><th>유형/기간</th><th>일수</th><th>결재단계</th><th>결재진행</th><th>승인·반려</th></tr>
<?php if(!$rows):?><tr><td colspan="7" class="muted" style="text-align:center;padding:36px">현재 결재할 신청이 없습니다.</td></tr><?php endif?>
<?php foreach($rows as $r):?>
<tr>
<td><?=h(trim($r['employee_name'].' '.$r['position']))?></td>
<td><?=h($r['department_name']?:'-')?></td>
<td><?=h($r['leave_name'])?><div class="muted"><?=h($r['start_date'])?> ~ <?=h($r['end_date'])?></div></td>
<td><?=fmt_days($r['requested_days'])?></td>
<td><span class="badge"><?=h($r['step_order'].'차 결재')?></span></td>
<td><div class="approval-flow"><?php foreach(array_filter(explode(' → ',(string)$r['approval_state'])) as $state):?><span><?=h(str_replace(['[waiting]','[pending]','[approved]','[rejected]','[skipped]'],['[대기]','[결재차례]','[승인]','[반려]','[종료]'],$state))?></span><?php endforeach?></div></td>
<td style="min-width:260px"><form method="post" onsubmit="return confirm(this.action.value==='reject'?'정말 반려하시겠습니까?':'현재 단계를 승인하시겠습니까?')"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$r['id']?>"><input name="comment" placeholder="결재의견 또는 반려사유" style="width:100%;margin-bottom:8px"><div class="actions"><button class="btn sm" name="action" value="approve">승인</button><button class="btn sm red" name="action" value="reject">반려</button></div></form></td>
</tr>
<?php endforeach?>
</table></div></div>

<div class="card" style="margin-top:20px">
<div class="card-head"><div><h2>최근 처리내역</h2><p class="muted">본인이 최근 승인하거나 반려한 내역입니다.</p></div></div>
<div class="table-wrap"><table class="table"><tr><th>처리일시</th><th>신청자</th><th>휴가</th><th>기간</th><th>단계</th><th>결과</th><th>의견</th></tr>
<?php if(!$completed):?><tr><td colspan="7" class="muted" style="text-align:center;padding:28px">처리내역이 없습니다.</td></tr><?php endif?>
<?php foreach($completed as $r):?><tr><td><?=h($r['acted_at']?:'-')?></td><td><?=h(trim($r['employee_name'].' '.$r['position']))?></td><td><?=h($r['leave_name'])?></td><td><?=h($r['start_date'].' ~ '.$r['end_date'])?></td><td><?=h($r['step_order'].'차')?></td><td><span class="badge"><?=h($r['status']==='approved'?'승인':'반려')?></span></td><td><?=h($r['comment']?:'-')?></td></tr><?php endforeach?>
</table></div></div>
<?php include '_bottom.php';?>
