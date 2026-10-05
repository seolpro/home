<?php
require __DIR__.'/config.php';
$msg=''; $url='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $memberNo=preg_replace('/\D/','',$_POST['member_no']??'');
    if (!preg_match('/^\d{7,8}$/',$memberNo)) $msg='조합원번호는 7~8자리 숫자로 입력하세요.';
    else {
        $token=bin2hex(random_bytes(32));
        $st=db()->prepare("INSERT INTO qr_test_members(member_no,qr_token,token_created_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE qr_token=VALUES(qr_token),token_created_at=NOW()");
        $st->execute([$memberNo,$token]);
        $url=base_url().'/qr.php?t='.rawurlencode($token);
        $msg='QR 열기 URL을 생성했습니다.';
    }
}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>QR 참석 테스트</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:760px"><h1 class="h3 fw-bold mb-2">총회 QR 참석 테스트</h1><p class="text-secondary">조합원번호를 입력해 개인 QR URL을 생성합니다.</p>
<div class="card shadow-sm"><div class="card-body p-4"><form method="post" class="row g-2"><div class="col"><input name="member_no" class="form-control form-control-lg" inputmode="numeric" maxlength="8" placeholder="조합원번호 7~8자리" required></div><div class="col-auto"><button class="btn btn-primary btn-lg">QR URL 생성</button></div></form>
<?php if($msg):?><div class="alert alert-info mt-3 mb-0"><?=h($msg)?></div><?php endif;?>
<?php if($url):?><div class="mt-3"><label class="form-label fw-bold">알림톡에 넣을 테스트 URL</label><div class="input-group"><input id="u" class="form-control" value="<?=h($url)?>" readonly><button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('u').value)" type="button">복사</button></div><a class="btn btn-success mt-3" href="<?=h($url)?>" target="_blank">QR 참석증 열기</a></div><?php endif;?></div></div>
<div class="d-flex gap-2 mt-3"><a href="scan.php" class="btn btn-dark">USB 스캐너 테스트</a>
<a href="mobile_scan.php"
   class="btn btn-success btn-lg">
    📱 모바일 QR 스캔하기
</a><a href="admin.php" class="btn btn-outline-dark">참석자 명부</a></div></main></body></html>
