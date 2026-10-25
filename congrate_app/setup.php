<?php
require __DIR__.'/bootstrap.php';
$err='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST')try{
 check_csrf();if(APP_SETUP_KEY==='CHANGE_THIS_TO_A_LONG_RANDOM_SECRET_BEFORE_UPLOAD'||!hash_equals(APP_SETUP_KEY,(string)($_POST['setup_key']??'')))throw new RuntimeException('설치키를 확인하세요.');
 $pdo=db();$sql=file_get_contents(__DIR__.'/schema.sql');foreach(array_filter(array_map('trim',explode(';',$sql))) as $statement)$pdo->exec($statement);
 $username=trim((string)($_POST['username']??''));$pw=(string)($_POST['password']??'');if(strlen($username)<3||strlen($pw)<12)throw new RuntimeException('아이디 3자 이상, 비밀번호 12자 이상');
 $st=$pdo->prepare('SELECT COUNT(*) FROM cg_admin');$st->execute();if((int)$st->fetchColumn()>0)throw new RuntimeException('이미 설치되었습니다. setup.php를 삭제하세요.');
 $pdo->prepare('INSERT INTO cg_admin(username,password_hash) VALUES(?,?)')->execute([$username,password_hash($pw,PASSWORD_DEFAULT)]);$ok='설치 완료! setup.php와 schema.sql을 서버에서 삭제한 후 로그인하세요.';
}catch(Throwable $e){$err=$e->getMessage();}
?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><body class="container py-5" style="max-width:560px"><h3>경조금 관리 설치</h3><p>config.php의 APP_SETUP_KEY를 먼저 변경하세요.</p><?php if($err):?><div class="alert alert-danger"><?=h($err)?></div><?php endif;if($ok):?><div class="alert alert-success"><?=h($ok)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input class="form-control my-2" name="setup_key" placeholder="설치키" required><input class="form-control my-2" name="username" placeholder="관리자 아이디" required><input class="form-control my-2" type="password" name="password" placeholder="비밀번호 12자 이상" required><button class="btn btn-primary">설치</button></form></body></html>
