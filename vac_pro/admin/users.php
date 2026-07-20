<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/app.php';
require_once __DIR__.'/../lib/account_recovery.php';
$pageTitle='권한계정 관리';
include '_top.php';
require_hr_admin();
$pdo=db();
$msg='';$err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $action=(string)($_POST['action']??'save');

        if($action==='reset_password'){
            if(!is_super_admin())throw new RuntimeException('알림톡 비밀번호 초기화는 최고관리자만 가능합니다.');
            $targetId=(int)($_POST['target_id']??0);
            $q=$pdo->prepare('SELECT id,username,name,phone,alimtalk_opt_in,password_hash FROM admins WHERE id=?');
            $q->execute([$targetId]);
            $target=$q->fetch();

            if(!$target){
                throw new RuntimeException('초기화할 계정을 찾지 못했습니다.');
            }

            $temporaryPassword=account_recovery_temporary_password();

            $pdo->beginTransaction();
            try{
                $pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?')
                    ->execute([
                        password_hash($temporaryPassword,PASSWORD_DEFAULT),
                        $targetId
                    ]);

                $sent=account_recovery_send_password_alimtalk(
                    $target,
                    $temporaryPassword
                );

                $pdo->commit();
            }catch(Throwable $e){
                if($pdo->inTransaction()){
                    $pdo->rollBack();
                }
                throw $e;
            }

            $msg=$target['name'].' 계정의 임시 비밀번호를 '
                .$sent['masked_phone'].' 번호로 발송했습니다.';
        }else{
            $id=(int)($_POST['id']??0);$username=trim((string)($_POST['username']??''));$name=trim((string)($_POST['name']??''));$role=(string)($_POST['role']??'approver');
            $employeeId=($_POST['employee_id']??'')!==''?(int)$_POST['employee_id']:null;$phone=trim((string)($_POST['phone']??''));$active=isset($_POST['is_active'])?1:0;
            if(!$username||!$name)throw new Exception('이름과 아이디를 입력하세요.');
            $pdo->beginTransaction();
            if($id){
                $sql='UPDATE admins SET username=?,name=?,role=?,employee_id=?,phone=?,is_active=?';$params=[$username,$name,$role,$employeeId,$phone,$active];
                if(($_POST['password']??'')!==''){
                    if(strlen((string)$_POST['password'])<8)throw new Exception('비밀번호는 8자 이상이어야 합니다.');
                    $sql.=',password_hash=?';$params[]=password_hash((string)$_POST['password'],PASSWORD_DEFAULT);
                }
                $sql.=' WHERE id=?';$params[]=$id;$pdo->prepare($sql)->execute($params);
            }else{
                if(strlen((string)($_POST['password']??''))<8)throw new Exception('신규 비밀번호는 8자 이상이어야 합니다.');
                $pdo->prepare('INSERT INTO admins(username,password_hash,name,role,employee_id,phone,is_active) VALUES(?,?,?,?,?,?,?)')->execute([$username,password_hash((string)$_POST['password'],PASSWORD_DEFAULT),$name,$role,$employeeId,$phone,$active]);
                $id=(int)$pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM admin_department_scopes WHERE admin_id=?')->execute([$id]);
            $ins=$pdo->prepare('INSERT INTO admin_department_scopes(admin_id,department_id) VALUES(?,?)');
            foreach(array_unique(array_map('intval',$_POST['department_ids']??[])) as $d)if($d)$ins->execute([$id,$d]);
            $pdo->commit();$msg='저장되었습니다.';
        }
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$err=$e->getMessage();}
}

$edit=null;$editDeps=[];
if(isset($_GET['edit'])){
    $s=$pdo->prepare('SELECT * FROM admins WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();
    if($edit){$s=$pdo->prepare('SELECT department_id FROM admin_department_scopes WHERE admin_id=?');$s->execute([$edit['id']]);$editDeps=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
}
$employees=$pdo->query('SELECT id,name,position,phone FROM employees WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();
$deps=$pdo->query('SELECT * FROM departments WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();
$users=$pdo->query("SELECT a.*,e.name employee_name,GROUP_CONCAT(d.name ORDER BY d.sort_order SEPARATOR ', ') scope_names FROM admins a LEFT JOIN employees e ON e.id=a.employee_id LEFT JOIN admin_department_scopes s ON s.admin_id=a.id LEFT JOIN departments d ON d.id=s.department_id GROUP BY a.id ORDER BY a.id")->fetchAll();
$roleLabels=['super_admin'=>'최고관리자','hr_admin'=>'인사관리자','department_manager'=>'부서관리자','approver'=>'결재권자','viewer'=>'조회자'];
?>
<div class="page-title-row"><div><h1>권한계정 관리</h1><p class="page-desc">관리자·결재권자의 로그인 아이디 확인, 권한 설정 및 알림톡 비밀번호 초기화를 관리합니다.</p></div></div>
<?php if($msg):?><div class="alert ok"><?=h($msg)?></div><?php endif?>
<?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?>

<div class="two-panel">
<section class="card"><h2><?=$edit?'계정 수정':'계정 등록'?></h2><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit['id']??0?>"><div class="form-grid cols-2"><label>이름<input name="name" required value="<?=h($edit['name']??'')?>"></label><label>아이디<input name="username" required value="<?=h($edit['username']??'')?>"></label><label>비밀번호<input type="password" name="password" placeholder="<?=$edit?'변경할 때만 입력':'8자 이상'?>"></label><label>권한직원 지정 <select name="employee_id"><option value="">연결 안 함</option><?php foreach($employees as $e):?><option value="<?=$e['id']?>" <?=($edit['employee_id']??'')==$e['id']?'selected':''?>><?=h($e['name'].' '.$e['position'])?></option><?php endforeach?></select></label><label>권한<select name="role"><?php foreach($roleLabels as $key=>$label):?><option value="<?=$key?>" <?=($edit['role']??'approver')===$key?'selected':''?>><?=h($label)?></option><?php endforeach?></select></label><label>휴대폰<input name="phone" value="<?=h($edit['phone']??'')?>" placeholder="01012345678"></label></div><div class="scope-box"><strong>조회 가능한 부서</strong><p class="muted">최고관리자·인사관리자는 모든 부서를 조회합니다. 그 외 권한은 아래 부서만 조회합니다.</p><div class="checkbox-grid"><?php foreach($deps as $d):?><label><input type="checkbox" name="department_ids[]" value="<?=$d['id']?>" <?=in_array((int)$d['id'],$editDeps,true)?'checked':''?>> <?=h($d['name'])?></label><?php endforeach?></div></div><label class="toggle-row"><input type="checkbox" name="is_active" <?=!$edit||$edit['is_active']?'checked':''?>><span><strong>계정 사용</strong></span></label><div class="form-actions"><button class="btn">저장</button><?php if($edit):?><a class="btn ghost" href="users.php">신규 등록</a><?php endif?></div></form></section>
<section class="card"><h2>등록 계정</h2><div class="table-wrap"><table class="table"><tr><th>이름/아이디</th><th>권한</th><th>직원</th><th>조회부서</th><th>상태</th><th>관리</th></tr><?php foreach($users as $u):?><tr><td><strong><?=h($u['name'])?></strong><div class="account-id-line">ID: <?=h($u['username'])?></div><div class="muted"><?=h($u['phone']?:'휴대폰 미등록')?></div></td><td><?=h($roleLabels[$u['role']]??$u['role'])?></td><td><?=h($u['employee_name']?:'-')?></td><td><?=h(in_array($u['role'],['super_admin','hr_admin'])?'전체':($u['scope_names']?:'-'))?></td><td><?=$u['is_active']?'사용':'중지'?></td><td><div class="actions"><a class="btn sm ghost" href="?edit=<?=$u['id']?>">수정</a><?php if(is_super_admin()):?><form method="post" onsubmit="return confirm('<?=h($u['name'])?> 계정의 임시 비밀번호를 등록된 휴대폰으로 알림톡 발송할까요?');"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="target_id" value="<?=$u['id']?>"><button class="btn sm red" type="submit">알림톡 비밀번호 초기화</button></form><?php endif?></div></td></tr><?php endforeach?></table></div></section>
</div>
<?php include '_bottom.php';?>
