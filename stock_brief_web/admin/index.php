<?php
require_once dirname(__DIR__) . '/lib.php';
admin_required();

$msg = '';
$err = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $market = (string)($_POST['market'] ?? 'KR_KOSPI');
            $name = trim((string)($_POST['name'] ?? ''));
            $symbol = trim((string)($_POST['symbol'] ?? ''));
            $quantity = (float)($_POST['quantity'] ?? 0);
            $avg = (float)($_POST['avg_price'] ?? 0);
            $keyword = trim((string)($_POST['news_keyword'] ?? ''));
            $active = isset($_POST['is_active']) ? 1 : 0;
            $sort = (int)($_POST['sort_order'] ?? 0);

            if (!in_array($market,['KR_KOSPI','KR_KOSDAQ','US'],true)) {
                throw new RuntimeException('시장 구분 오류');
            }
            if ($name==='' || $symbol==='') {
                throw new RuntimeException('종목명과 종목코드는 필수입니다.');
            }

            if ($id>0) {
                $s = db()->prepare(
                    "UPDATE portfolio
                     SET market=?,name=?,symbol=?,quantity=?,avg_price=?,news_keyword=?,is_active=?,sort_order=?,updated_at=NOW()
                     WHERE id=?"
                );
                $s->execute([$market,$name,$symbol,$quantity,$avg,$keyword,$active,$sort,$id]);
                $msg='종목을 수정했습니다.';
            } else {
                $s = db()->prepare(
                    "INSERT INTO portfolio(
                        market,name,symbol,quantity,avg_price,news_keyword,is_active,sort_order,created_at,updated_at
                     ) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())"
                );
                $s->execute([$market,$name,$symbol,$quantity,$avg,$keyword,$active,$sort]);
                $msg='종목을 등록했습니다. 등록 개수 제한은 없습니다.';
            }
        }

        if ($action==='delete') {
            $id=(int)($_POST['id']??0);
            db()->prepare("DELETE FROM portfolio WHERE id=?")->execute([$id]);
            $msg='종목을 삭제했습니다.';
        }

        if ($action === 'test_alimtalk') {
            $phone = preg_replace('/\D/', '', (string)cfg('brief.recipient_phone'));
            $name = trim((string)cfg('brief.recipient_name')) ?: '관리자';
            if (!preg_match('/^01\d{8,9}$/', $phone)) {
                throw new RuntimeException('config.php의 brief.recipient_phone 수신번호를 확인하세요.');
            }
            $result = send_brief_alimtalk($phone, $name);
            log_alimtalk(date('Y-m-d'), $phone, array_merge($result, ['test'=>true]));
            if (!empty($result['ok'])) {
                $msg = '알림톡 테스트 발송 요청이 성공했습니다. 수신 휴대폰을 확인해 주세요.';
            } else {
                $detail = $result['message'] ?? '알림톡 테스트 발송 실패';
                $resp = $result['response'] ?? null;
                if (is_array($resp)) $resp = json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                if ($resp) $detail .= ' / 응답: '.$resp;
                throw new RuntimeException($detail);
            }
        }
    }
} catch(Throwable $e) {
    $err=$e->getMessage();
}

$edit=null;
if (!empty($_GET['edit'])) {
    $s=db()->prepare("SELECT * FROM portfolio WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $edit=$s->fetch()?:null;
}

$rows=db()->query("SELECT * FROM portfolio ORDER BY sort_order ASC,id ASC")->fetchAll();
$countAll=count($rows);
$countActive=count(array_filter($rows,fn($r)=>!empty($r['is_active'])));
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/app.css">
<title>포트폴리오 관리</title>
</head>
<body>
<?php include '_top.php';?>
<main class="wrap">

<h1>포트폴리오 관리</h1>

<div class="card">
    <b>등록 종목 <?=$countAll?>개 / 브리핑 사용 <?=$countActive?>개</b><br>
    활성 종목을 기준으로 매일 웹 브리핑을 생성합니다.
    <form method="post" style="margin-top:12px" onsubmit="return confirm('config.php에 설정된 수신번호로 알림톡 테스트를 1건 발송할까요?');">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>">
        <input type="hidden" name="action" value="test_alimtalk">
        <button type="submit" class="btn green">📨 알림톡 테스트 발송</button>
        <span style="margin-left:8px;font-size:.9rem;opacity:.75">수신: <?=e((string)cfg('brief.recipient_name'))?> / <?=e((string)cfg('brief.recipient_phone'))?></span>
    </form>
</div>

<?php if($msg):?><div class="ok"><?=e($msg)?></div><?php endif?>
<?php if($err):?><div class="err"><?=e($err)?></div><?php endif?>

<section class="card">
<h2><?= $edit?'종목 수정':'종목 추가' ?></h2>
<form method="post" class="grid">
<input type="hidden" name="csrf" value="<?=e(csrf())?>">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=e($edit['id']??'')?>">

<label>시장
<select name="market">
<?php foreach(['KR_KOSPI'=>'한국 KOSPI','KR_KOSDAQ'=>'한국 KOSDAQ','US'=>'미국'] as $k=>$v):?>
<option value="<?=$k?>" <?=($edit['market']??'KR_KOSPI')===$k?'selected':''?>><?=$v?></option>
<?php endforeach?>
</select>
</label>

<label>종목명
<input name="name" value="<?=e($edit['name']??'')?>" required>
</label>

<label>종목코드
<input name="symbol" value="<?=e($edit['symbol']??'')?>" placeholder="005930 / AAPL" required>
</label>

<label>보유수량
<input type="number" step="0.000001" name="quantity" value="<?=e($edit['quantity']??'0')?>">
</label>

<label>평균매입가
<input type="number" step="0.0001" name="avg_price" value="<?=e($edit['avg_price']??'0')?>">
</label>

<label>뉴스 검색어
<input name="news_keyword" value="<?=e($edit['news_keyword']??'')?>" placeholder="비워두면 종목명 사용">
</label>

<label>정렬순서
<input type="number" name="sort_order" value="<?=e($edit['sort_order']??'0')?>">
</label>

<label style="display:flex;gap:8px;align-items:center">
<input style="width:auto" type="checkbox" name="is_active" value="1"
<?=!isset($edit['is_active'])||!empty($edit['is_active'])?'checked':''?>>
브리핑 사용
</label>

<div class="full actions">
<button class="btn green">저장</button>
<?php if($edit):?><a class="btn gray" href="index.php">취소</a><?php endif?>
</div>
</form>
</section>

<section class="card">
<div class="actions" style="justify-content:space-between;align-items:center">
<h2>등록 종목 전체</h2>
<form method="post"
      action="../api/morning_brief.php?mode=send&amp;key=<?=e(rawurlencode((string)cfg('security.cron_key')))?>"
      target="_blank"
      onsubmit="return confirm('지금 브리핑을 생성하고 알림톡을 발송할까요?');">

    <button class="btn green" type="submit">
        📈 지금 브리핑 생성·알림톡 발송
    </button>
</form>
</div>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>사용</th><th>시장</th><th>종목</th><th>코드</th>
<th>수량</th><th>평균매입가</th><th>뉴스검색어</th><th>관리</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $r):?>
<tr>
<td><?=$r['is_active']?'사용':'중지'?></td>
<td><?=e($r['market'])?></td>
<td><?=e($r['name'])?></td>
<td><?=e($r['symbol'])?></td>
<td><?=e($r['quantity'])?></td>
<td><?=e($r['avg_price'])?></td>
<td><?=e($r['news_keyword'])?></td>
<td class="actions">
<a class="btn gray" href="?edit=<?=$r['id']?>">수정</a>
<form method="post" onsubmit="return confirm('삭제할까요?')">
<input type="hidden" name="csrf" value="<?=e(csrf())?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button class="btn red">삭제</button>
</form>
</td>
</tr>
<?php endforeach?>

<?php if(!$rows):?>
<tr><td colspan="8">등록된 종목이 없습니다.</td></tr>
<?php endif?>
</tbody>
</table>
</div>
</section>

</main>
</body>
</html>
