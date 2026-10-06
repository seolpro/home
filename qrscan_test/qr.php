<?php
require __DIR__.'/config.php';
$t=$_GET['t']??'';
$st=db()->prepare("SELECT member_no,qr_token FROM qr_test_members WHERE qr_token=? LIMIT 1"); $st->execute([$t]); $m=$st->fetch();
if(!$m){http_response_code(404);exit('유효하지 않은 QR URL입니다.');}
// QR payload: 임의문자 + 조합원번호 + 임의문자. 서버는 전체 문자열에서 직접 번호를 신뢰하지 않고 DB token으로 검증.
$payload='AT-'.substr($m['qr_token'],0,16).'-'.$m['member_no'].'-'.substr($m['qr_token'],16,16);
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>QR 참석증</title><script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script><style>body{font-family:system-ui;text-align:center;background:#f5f6f8;margin:0}.card{max-width:420px;margin:40px auto;background:white;padding:28px;border-radius:22px;box-shadow:0 8px 30px #0001}#qrcode{display:flex;justify-content:center;margin:25px}.timer{font-size:30px;font-weight:800}.expired{display:none;font-size:22px;color:#c00;font-weight:800;padding:50px 0}</style></head><body><div class="card"><h2>총회 QR 참석증</h2><p>접수처 QR 리더기에 화면을 보여주세요.</p><div id="live"><div id="qrcode"></div><div><span class="timer" id="sec">30</span>초 후 화면이 닫힙니다.</div></div><div id="expired" class="expired">QR 표시시간이 종료되었습니다.<br><small>알림톡의 버튼을 다시 눌러주세요.</small></div></div>
<script>new QRCode(document.getElementById('qrcode'),{text:<?=json_encode($payload,JSON_UNESCAPED_SLASHES)?>,width:260,height:260,correctLevel:QRCode.CorrectLevel.M});let s=30;const el=document.getElementById('sec');const timer=setInterval(()=>{s--;el.textContent=s;if(s<=0){clearInterval(timer);document.getElementById('live').style.display='none';document.getElementById('expired').style.display='block';}},1000);</script></body></html>
