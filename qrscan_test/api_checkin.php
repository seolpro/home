<?php
require __DIR__.'/config.php'; header('Content-Type: application/json; charset=utf-8');
$code=trim($_POST['code']??'');
if(!preg_match('/^AT-([a-f0-9]{16})-(\d{7,8})-([a-f0-9]{16})$/i',$code,$mm)){echo json_encode(['ok'=>false,'message'=>'올바른 참석 QR이 아닙니다.'],JSON_UNESCAPED_UNICODE);exit;}
$memberNo=$mm[2]; $prefix=$mm[1]; $suffix=$mm[3];
$st=db()->prepare("SELECT member_no,qr_token FROM qr_test_members WHERE member_no=? LIMIT 1");$st->execute([$memberNo]);$m=$st->fetch();
if(!$m || !hash_equals(substr($m['qr_token'],0,16),strtolower($prefix)) || !hash_equals(substr($m['qr_token'],16,16),strtolower($suffix))){echo json_encode(['ok'=>false,'message'=>'QR 검증에 실패했습니다.'],JSON_UNESCAPED_UNICODE);exit;}
try{$st=db()->prepare("INSERT INTO qr_test_attendance(member_no,scanned_code) VALUES(?,?)");$st->execute([$memberNo,$code]);echo json_encode(['ok'=>true,'message'=>'✓ 참석 등록 완료<br><small>조합원번호 '.h($memberNo).'</small>'],JSON_UNESCAPED_UNICODE);}catch(PDOException $e){if(($e->errorInfo[1]??0)==1062){$st=db()->prepare("SELECT checked_at FROM qr_test_attendance WHERE member_no=?");$st->execute([$memberNo]);$a=$st->fetch();echo json_encode(['ok'=>false,'message'=>'이미 참석 등록되었습니다.<br><small>'.h($memberNo).' / '.h($a['checked_at']??'').'</small>'],JSON_UNESCAPED_UNICODE);}else throw $e;}
