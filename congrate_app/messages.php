<?php
require __DIR__.'/bootstrap.php';auth();require __DIR__.'/layout.php';require_once __DIR__.'/ppurio.php';
if(isset($_GET['reset'])){unset($_SESSION['cg_prepared']);redirect('messages.php');}
$pdo=db();$msg='';$err='';$prepared=$_SESSION['cg_prepared']??null;
if($_SERVER['REQUEST_METHOD']==='POST')try{
 check_csrf();$action=(string)($_POST['action']??'');
 if($action==='prepare'){
  $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['ids']??[])))));
  $body=trim((string)($_POST['message']??''));if(!$ids||$body==='')throw new RuntimeException('수신자와 메시지를 입력하세요.');
  if(mb_strlen($body)>1800)throw new RuntimeException('메시지는 1800자 이하로 작성하세요.');
  $st=$pdo->prepare('SELECT id,name,phone FROM cg_people WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$st->execute($ids);
  $people=array_values(array_filter($st->fetchAll(),fn($p)=>preg_match('/^01[0-9]{8,9}$/',(string)$p['phone'])));
  if(!$people)throw new RuntimeException('유효한 수신번호가 없습니다.');
  $prepared=['people'=>$people,'message'=>$body,'nonce'=>bin2hex(random_bytes(16)),'at'=>time()];$_SESSION['cg_prepared']=$prepared;$msg='발송 전 검토 내용을 확인하세요.';
 }elseif($action==='send'){
  if(!$prepared||!hash_equals($prepared['nonce'],(string)($_POST['nonce']??'')))throw new RuntimeException('발송 검토가 만료되었습니다. 다시 준비하세요.');
  if(empty($_POST['consent']))throw new RuntimeException('수신 동의·연락처 확인에 체크하세요.');
  if(time()-$prepared['at']>1800)throw new RuntimeException('30분이 지나 재검토가 필요합니다.');
  unset($_SESSION['cg_prepared']);$success=0;$fail=0;
  foreach($prepared['people'] as $person){
   try{$result=cg_ppurio_send((string)$person['phone'],(string)$person['name'],$prepared['message']);}catch(Throwable $e){$result=['ok'=>false,'detail'=>$e->getMessage()];}
   $status=$result['ok']?'accepted':'failed';$success+=(int)$result['ok'];$fail+=(int)!$result['ok'];
   $pdo->prepare("INSERT INTO cg_message_logs(person_id,channel,message,status,result) VALUES(?,'SMS',?,?,?)")->execute([$person['id'],$prepared['message'],$status,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  }
  $msg="발송 요청 접수 {$success}건 / 실패 {$fail}건 (최종 수신 여부는 뿌리오 결과 조회 필요)";$prepared=null;
 }else throw new RuntimeException('잘못된 요청입니다.');
}catch(Throwable $e){$err=$e->getMessage();}
$people=$pdo->query('SELECT id,name,phone FROM cg_people ORDER BY name')->fetchAll();$people=array_values(array_filter($people,fn($p)=>preg_match('/^01[0-9]{8,9}$/',(string)$p['phone'])));
$logs=$pdo->query('SELECT l.created_at,p.name,l.status,l.result FROM cg_message_logs l JOIN cg_people p ON p.id=l.person_id ORDER BY l.id DESC LIMIT 30')->fetchAll();
top('경조사 문자 발송');if($msg)echo '<div class="alert alert-info">'.h($msg).'</div>';if($err)echo '<div class="alert alert-danger">'.h($err).'</div>';
if($prepared):?>
<div class="card p-4 mb-4"><h5>최종 발송 검토</h5><p>대상: <b><?=count($prepared['people'])?>명</b></p><div class="border p-3 rounded mb-3" style="white-space:pre-wrap"><?=h($prepared['message'])?></div><div class="mb-3"><?php foreach($prepared['people'] as $person):?><span class="badge text-bg-light me-1 mb-1"><?=h($person['name'])?> (<?=h(substr($person['phone'],0,3).'****'.substr($person['phone'],-4))?>)</span><?php endforeach;?></div><form method="post" onsubmit="return confirm('선택한 <?=count($prepared['people'])?>명에게 실제 문자를 발송하시겠습니까?');"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="send"><input type="hidden" name="nonce" value="<?=h($prepared['nonce'])?>"><label class="d-block mb-3"><input type="checkbox" name="consent" value="1" required> 수신자 연락처 및 경조사 안내 수신 동의 여부를 확인했습니다.</label><button class="btn btn-danger">실제 문자 발송</button> <a href="messages.php?reset=1" class="btn btn-outline-secondary">다시 작성</a></form></div>
<?php else: ?>
<div class="card p-4 mb-4"><p class="text-muted">뿌리오 SMS/LMS로 발송합니다. 문자 발송 비용이 발생할 수 있습니다. 발신번호는 사전에 뿌리오에 등록해야 합니다.</p><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="prepare"><label class="fw-bold">안내문</label><textarea name="message" class="form-control mb-3" rows="6" maxlength="1800" required placeholder="안녕하세요. 저의 경조사 소식을 전합니다."></textarea><label class="fw-bold">수신자 (<?=count($people)?>명)</label><div class="border rounded p-3 mb-3" style="max-height:300px;overflow:auto"><label class="d-block mb-2"><input type="checkbox" id="all" onclick="document.querySelectorAll('.recipient').forEach(x=>x.checked=this.checked)"> 전체 선택</label><?php foreach($people as $person):?><label class="d-block mb-2"><input class="recipient" type="checkbox" name="ids[]" value="<?=$person['id']?>"> <?=h($person['name'])?> (<?=h(substr($person['phone'],0,3).'****'.substr($person['phone'],-4))?>)</label><?php endforeach;?></div><button class="btn btn-primary">발송 전 검토</button></form></div>
<?php endif; ?>
<div class="card p-3"><h5>최근 발송이력</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>일시</th><th>이름</th><th>상태</th><th>응답</th></tr></thead><tbody><?php foreach($logs as $l):?><tr><td><?=h($l['created_at'])?></td><td><?=h($l['name'])?></td><td><?=h($l['status'])?></td><td><small><?=h(mb_strimwidth((string)$l['result'],0,150,'…','UTF-8'))?></small></td></tr><?php endforeach;?></tbody></table></div></div>
<?php bottom();
