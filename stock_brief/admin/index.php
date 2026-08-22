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
    종목 등록 개수 제한은 없습니다. 종목이 많아 문자 길이가 길어지면 여러 건의 LMS로 자동 분할됩니다.
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
<form method="post" action="../api/morning_brief.php?mode=send&key=<?=e((string)cfg('security.gas_key'))?>" target="_blank">
<button class="btn green" type="submit">지금 테스트 문자 보내기</button>
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
