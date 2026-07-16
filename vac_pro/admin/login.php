<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $s=db()->prepare('SELECT * FROM admins WHERE username=? AND is_active=1');
        $s->execute([trim((string)($_POST['username'] ?? ''))]);
        $u=$s->fetch();
        if(!$u||!password_verify((string)($_POST['password'] ?? ''),(string)$u['password_hash'])){
            throw new Exception('로그인 정보가 올바르지 않습니다.');
        }
        $_SESSION['admin']=[
            'id'=>(int)$u['id'],
            'name'=>$u['name'],
            'role'=>$u['role'],
            'employee_id'=>(int)($u['employee_id']??0)
        ];
        db()->prepare('UPDATE admins SET last_login=NOW() WHERE id=?')->execute([$u['id']]);
        header('Location: dashboard.php');exit;
    }catch(Throwable $e){$err=$e->getMessage();}
}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="../assets/style.css?v=<?=filemtime(__DIR__.'/../assets/style.css')?>"><title>관리자 로그인</title></head><body><div class="login"><div class="card"><h1>관리자·결재자 로그인</h1><p class="muted">권한에 따라 조회 가능한 메뉴와 부서가 자동으로 제한됩니다.</p><?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><label>아이디<input name="username" required autocomplete="username"></label><label>비밀번호<input type="password" name="password" required autocomplete="current-password"></label><button class="btn">로그인</button></form><div class="login-help" aria-label="계정 복구 메뉴">
        <a class="login-help-link" href="find_account.php?mode=find">
            <span class="login-help-icon">ID</span>
            <span>아이디 찾기</span>
        </a>
        <a class="login-help-link" href="find_account.php?mode=reset">
            <span class="login-help-icon">PW</span>
            <span>비밀번호 초기화</span>
        </a>
    </div></div></div></body></html>
