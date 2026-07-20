<?php
require_once __DIR__.'/../lib/app.php';
require_once __DIR__.'/../lib/notify.php';
$pageTitle='신청·결재';
include '_top.php';
$pdo=db();
$msg='';
$err='';

function approval_status_label(string $status): string {
    return [
        'waiting'=>'대기', 'pending'=>'결재차례', 'approved'=>'승인',
        'rejected'=>'반려', 'skipped'=>'종료'
    ][$status] ?? $status;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $id=(int)($_POST['id']??0);
    $action=(string)($_POST['action']??'');
    $employeeId=admin_employee_id();

    $pdo->beginTransaction();
    try{
        if($employeeId<1){
            throw new RuntimeException('현재 로그인 계정이 직원정보와 연결되어 있지 않습니다. 권한계정 관리에서 직원을 연결하세요.');
        }

        $s=$pdo->prepare('SELECT lr.*,e.phone,e.name employee_name,e.position,e.department_id,e.alimtalk_opt_in,lt.name leave_name
                          FROM leave_requests lr
                          JOIN employees e ON e.id=lr.employee_id
                          JOIN leave_types lt ON lt.id=lr.leave_type_id
                          WHERE lr.id=? FOR UPDATE');
        $s->execute([$id]);
        $r=$s->fetch();
        if(!$r) throw new RuntimeException('신청을 찾을 수 없습니다.');
        if(!in_array($r['status'],['pending','in_approval'],true)) throw new RuntimeException('이미 완료된 신청입니다.');

        // 현재 단계 한 건만 잠금. 관리자도 결재단계를 건너뛸 수 없습니다.
        $a=$pdo->prepare("SELECT ra.*,ae.name approver_name,ae.position approver_position
                          FROM request_approvals ra
                          JOIN employees ae ON ae.id=ra.approver_employee_id
                          WHERE ra.request_id=? AND ra.status='pending'
                          ORDER BY ra.step_order ASC LIMIT 1 FOR UPDATE");
        $a->execute([$id]);
        $ap=$a->fetch();
        if(!$ap) throw new RuntimeException('현재 결재차례가 설정되지 않았습니다. 결재단계를 확인하세요.');
        if((int)$ap['approver_employee_id']!==$employeeId){
            throw new RuntimeException($ap['step_order'].'차 결재권자 '.$ap['approver_name'].' '.$ap['approver_position'].'님의 결재 차례입니다.');
        }
        if((int)$r['current_step']!==(int)$ap['step_order']){
            throw new RuntimeException('신청서의 현재 결재단계와 결재이력이 일치하지 않습니다. 관리자에게 문의하세요.');
        }

        // 이전 단계가 모두 승인인지 재검증
        $prev=$pdo->prepare("SELECT COUNT(*) FROM request_approvals
                            WHERE request_id=? AND step_order<? AND status<>'approved'");
        $prev->execute([$id,$ap['step_order']]);
        if((int)$prev->fetchColumn()>0) throw new RuntimeException('이전 결재단계가 완료되지 않았습니다.');

        $comment=trim((string)($_POST['comment']??''));
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
            $nextApproval=$next->fetch();
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

        if(in_array($event,['approved','rejected'],true) && $r['alimtalk_opt_in']){
            send_alimtalk($event,'employee',$r['employee_id'],$r['phone'],[
                'var1'=>trim($r['employee_name'].' '.$r['position']),
                'var2'=>$r['leave_name'],
                'var3'=>$r['start_date'],
                'var4'=>$r['end_date'],
                'var5'=>fmt_days($r['requested_days']),
                'var6'=>$comment?:'-'
            ]);
        }
        if($event==='approved'){
            send_final_approval_broadcast($r);
        }
        if($event==='next_approval' && $nextApproval){
            $q=$pdo->prepare('SELECT name,position,phone,alimtalk_opt_in FROM employees WHERE id=?');
            $q->execute([$nextApproval['approver_employee_id']]);
            $na=$q->fetch();
            if($na && $na['alimtalk_opt_in']){
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

[$scopeSql, $scopeParams] = request_scope_sql('e');
$status = trim((string)($_GET['status'] ?? ''));

$where = [];
$params = [];

// 기본 조회범위
if (trim((string)$scopeSql) !== '') {
    $where[] = $scopeSql;
    $params = array_merge($params, $scopeParams);
}

// 상태 필터
if ($status !== '') {
    $where[] = 'lr.status = ?';
    $params[] = $status;
}

$myEmployeeId = (int)(admin_employee_id() ?? 0);

// 부서관리자·결재권자 조회범위
if (!can_manage_hr()) {
    $deptIds = array_values(array_filter(
        array_map('intval', accessible_department_ids()),
        static fn($id) => $id > 0
    ));

    $scopeParts = [];

    if ($deptIds) {
        $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
        $scopeParts[] = "e.department_id IN ($placeholders)";
        foreach ($deptIds as $deptId) {
            $params[] = $deptId;
        }
    }

    if ($myEmployeeId > 0) {
        $scopeParts[] = "EXISTS (
            SELECT 1
            FROM request_approvals mine
            WHERE mine.request_id = lr.id
              AND mine.approver_employee_id = ?
        )";
        $params[] = $myEmployeeId;
    }

    if ($scopeParts) {
        $where[] = '(' . implode(' OR ', $scopeParts) . ')';
    } else {
        $where[] = '1 = 0';
    }
}

$whereSql = $where ? implode(' AND ', $where) : '1 = 1';

// 집계 별칭(my_turn)은 외부 SELECT에서 정렬하여 MariaDB/MySQL 호환성 확보
$sql = "
    SELECT request_list.*
    FROM (
        SELECT
            lr.*,
            e.name AS employee_name,
            e.position,
            d.name AS department_name,
            lt.name AS leave_name,
            GROUP_CONCAT(
                CONCAT(
                    ra.step_order,
                    '차 ',
                    COALESCE(ae.name, '미지정'),
                    ' ',
                    COALESCE(ae.position, ''),
                    ' [',
                    ra.status,
                    ']'
                )
                ORDER BY ra.step_order
                SEPARATOR ' → '
            ) AS approval_state,
            MAX(
                CASE
                    WHEN ra.approver_employee_id = ?
                     AND ra.status = 'pending'
                     AND ra.step_order = lr.current_step
                    THEN 1
                    ELSE 0
                END
            ) AS my_turn
        FROM leave_requests lr
        INNER JOIN employees e ON e.id = lr.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
        LEFT JOIN request_approvals ra ON ra.request_id = lr.id
        LEFT JOIN employees ae ON ae.id = ra.approver_employee_id
        WHERE {$whereSql}
        GROUP BY lr.id, e.name, e.position, d.name, lt.name
    ) AS request_list
    ORDER BY request_list.my_turn DESC, request_list.id DESC
    LIMIT 500
";

$executeParams = array_merge([$myEmployeeId], $params);
$s = $pdo->prepare($sql);
$s->execute($executeParams);
$rows = $s->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="page-title-row"><div><h1>신청·결재 관리</h1><p class="page-desc">결재는 반드시 1차 → 2차 → 3차 순서로 진행됩니다. 현재 차례의 결재권자만 승인·반려할 수 있습니다.</p></div></div>
<?php if($msg):?><div class="alert ok"><?=h($msg)?></div><?php endif?>
<?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?>
<form class="filter-bar" method="get"><label>상태<select name="status"><option value="">전체</option><?php foreach(['in_approval'=>'결재중','approved'=>'승인','rejected'=>'반려','cancelled'=>'취소'] as $k=>$v):?><option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=$v?></option><?php endforeach?></select></label><button class="btn">조회</button></form>
<div class="card"><div class="table-wrap"><table class="table"><tr><th>신청자</th><th>부서</th><th>유형/기간</th><th>일수</th><th>현재단계</th><th>결재진행</th><th>처리</th></tr>
<?php foreach($rows as $r):?><tr>
<td><?=h($r['employee_name'].' '.$r['position'])?></td><td><?=h($r['department_name']?:'-')?></td>
<td><?=h($r['leave_name'])?><div class="muted"><?=h($r['start_date'])?> ~ <?=h($r['end_date'])?></div><?php if(!empty($r['evidence_path'])):?><a class="evidence-link" href="evidence.php?id=<?=$r['id']?>" target="_blank" rel="noopener">📎 <?=h($r['evidence_name']?:'증빙서류 보기')?></a><?php endif?></td>
<td><?=fmt_days($r['requested_days'])?></td>
<td><span class="badge"><?=in_array($r['status'],['pending','in_approval'],true)?h($r['current_step'].'차 결재'):h($r['status'])?></span></td>
<td>
<div class="approval-flow">
<?php
$approvalStates = array_values(array_filter(
    explode(' → ', (string)($r['approval_state'] ?? '')),
    static fn($v) => trim($v) !== ''
));
?>
<?php if ($approvalStates): ?>
    <?php foreach ($approvalStates as $state): ?>
        <span><?=h(str_replace(
            ['[waiting]','[pending]','[approved]','[rejected]','[skipped]'],
            ['[대기]','[결재차례]','[승인]','[반려]','[종료]'],
            $state
        ))?></span>
    <?php endforeach ?>
<?php else: ?>
    <span class="muted">결재단계 없음</span>
<?php endif ?>
</div>
</td>
<td>
<?php
$isProcessing = in_array($r['status'], ['pending','in_approval'], true);
$isMyTurn = (int)($r['my_turn'] ?? 0) === 1;
?>
<?php if ($isProcessing && $isMyTurn): ?>
<form method="post">
    <input type="hidden" name="csrf" value="<?=csrf_token()?>">
    <input type="hidden" name="id" value="<?=$r['id']?>">
    <input name="comment" placeholder="결재의견 또는 반려사유">
    <div class="actions">
        <button class="btn sm" name="action" value="approve">현재 단계 승인</button>
        <button class="btn sm red" name="action" value="reject">반려</button>
    </div>
</form>
<?php elseif ($isProcessing): ?>
    <span class="muted"><?=h($r['current_step'])?>차 결재 대기</span>
<?php else: ?>
    <span class="muted">처리 완료</span>
<?php endif ?>
</td>
</tr><?php endforeach?></table></div></div>
<?php include '_bottom.php';?>
