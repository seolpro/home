<?php require __DIR__.'/common.php';
$token=(string)($_GET['key']??$_POST['key']??'');
$q=db()->prepare('SELECT * FROM dv_recipients WHERE token=? AND active=1');$q->execute([$token]);$r=$q->fetch();
if(!$r){http_response_code(404);layout_start('접속할 수 없습니다');echo '<section class="card">유효하지 않거나 중지된 신청 링크입니다.</section>';layout_end();exit;}
$error='';$success=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $market=post('market');$other=post('other_market');$date=post('visit_date');$vehicle=mb_strtoupper(preg_replace('/\s+/u','',post('vehicle_no')));
 if($market==='기타')$market=$other;
 $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);$today=new DateTimeImmutable('today');
 if(!in_array(post('market'),['쿠팡','네이버','컬리','기타'],true)||mb_strlen($market)<1||mb_strlen($market)>80)$error='온라인마켓을 확인해 주세요.';
 elseif(!$d||$d->format('Y-m-d')!==$date||$d<$today||$d>$today->modify('+60 days'))$error='방문일은 오늘부터 60일 이내로 선택해 주세요.';
 elseif(!preg_match('/^[가-힣A-Za-z0-9-]{4,20}$/u',$vehicle))$error='차량번호를 정확하게 입력해 주세요.';
 else{
  $now=date('Y-m-d H:i:s');$pdo=db();$stmt=$pdo->prepare('INSERT INTO dv_requests(recipient_id,market,visit_date,vehicle_no,created_at) VALUES(?,?,?,?,?)');$stmt->execute([$r['id'],$market,$date,$vehicle,$now]);$id=(int)$pdo->lastInsertId();
  $result=ppurio_send($r['recipient_phone'],compact('market')+['visit_date'=>$date,'vehicle_no'=>$vehicle,'created_at'=>$now]);
  $stmt=$pdo->prepare('UPDATE dv_requests SET send_status=?,send_message=?,send_http=?,sent_at=IF(?="accepted",NOW(),NULL) WHERE id=?');$status=$result['ok']?'accepted':'failed';$stmt->execute([$status,$result['message'],$result['http']??null,$status,$id]);$success=true;
 }
}
layout_start('🅿️배송 차량 사전 출입 주차등록 요청');
if($success){echo '<section class="card"><h2>신청이 접수되었습니다.</h2><p>차량등록 요청이 저장되었습니다. 수신인에게 알림톡이 전달되는 데 시간이 걸릴 수 있으며, 실제 아파트 차량등록 완료를 의미하지 않습니다.</p><a class="btn" href="'.h(url('index.php?key='.$token)).'">추가 요청</a></section>';}
else{echo '<section class="card"><p class="muted">배송기사는 아래 정보를 입력해 주세요. 실제 출입 승인은 입주자 또는 관리사무소에서 처리합니다.</p><p>등록 요청 대상: <b>'.h($r['label']).'</b></p>';
if($error)echo '<p class="error">'.h($error).'</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.h(csrf()).'"><input type="hidden" name="key" value="'.h($token).'"><label>온라인마켓 *</label><select name="market" id="market" required><option value="">선택</option>';
foreach(['쿠팡','네이버','컬리','기타(직접입력)'] as $m)echo '<option value="'.h($m).'" '.(post('market')===$m?'selected':'').'>'.h($m).'</option>';
echo '</select><div id="otherWrap" hidden><label>기타 마켓명 *</label><input name="other_market" id="other" maxlength="80" value="'.h(post('other_market')).'"></div><label>방문예정일 *</label><input type="date" name="visit_date" min="'.date('Y-m-d').'" max="'.date('Y-m-d',strtotime('+60 days')).'" value="'.h(post('visit_date')).'" required><label>등록차량번호 *</label><input name="vehicle_no" maxlength="20" placeholder="예: 123가4567" value="'.h(post('vehicle_no')).'" required><button type="submit">차량등록 요청하기</button></form></section><script>const m=document.getElementById("market"),w=document.getElementById("otherWrap"),o=document.getElementById("other");function sync(){w.hidden=m.value!=="기타";o.required=!w.hidden}m.addEventListener("change",sync);sync();</script>';}
layout_end();
