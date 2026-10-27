<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php'; auth();
require __DIR__.'/layout.php';
$pdo=db(); $error=''; $notice='';
$ddl="CREATE TABLE IF NOT EXISTS cg_mother_receipts (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 amount_thousand INT NOT NULL,
 memo VARCHAR(255) NOT NULL DEFAULT '',
 import_key CHAR(64) NULL UNIQUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_name(name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
try {
  // 기존 테이블과 분리. 이 페이지 최초 접속 시 전용 테이블만 생성
  $pdo->exec($ddl);
  if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf(); $action=(string)($_POST['action']??'');
    if($action==='import'){
      if(!isset($_FILES['csv']) || $_FILES['csv']['error']!==UPLOAD_ERR_OK)throw new RuntimeException('CSV 업로드 오류');
      if($_FILES['csv']['size']>2*1024*1024)throw new RuntimeException('2MB 이하 CSV만 가능합니다.');
      if(strtolower(pathinfo($_FILES['csv']['name'],PATHINFO_EXTENSION))!=='csv')throw new RuntimeException('CSV 파일만 업로드하세요.');
      $f=fopen($_FILES['csv']['tmp_name'],'rb');if(!$f)throw new RuntimeException('파일 읽기 실패');
      $header=fgetcsv($f); if(!$header)throw new RuntimeException('CSV 헤더가 없습니다.');
      $header=array_map(fn($s)=>trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$s)),$header);
      $ni=array_search('성명',$header,true);$ai=array_search('수령금액(천원)',$header,true);$mi=array_search('비고',$header,true);
      if($ni===false||$ai===false)throw new RuntimeException('CSV 열은 성명, 수령금액(천원), 비고 순서로 준비해 주세요.');
      $stmt=$pdo->prepare('INSERT IGNORE INTO cg_mother_receipts(name,amount_thousand,memo,import_key) VALUES(?,?,?,?)');
      $added=0;$skipped=0;$line=1;$pdo->beginTransaction();
      try {
        while(($cells=fgetcsv($f))!==false){$line++;if(count($cells)===1&&trim((string)$cells[0])==='')continue;
          $name=trim((string)($cells[$ni]??''));$raw=trim((string)($cells[$ai]??''));$memo=$mi===false?'':trim((string)($cells[$mi]??''));
          if($name===''||!preg_match('/^\d+$/',str_replace(',','',$raw)))throw new RuntimeException($line.'행의 성명 또는 금액이 잘못되었습니다.');
          $amount=(int)str_replace(',','',$raw);if($amount<=0||$amount>100000000)throw new RuntimeException($line.'행 금액 오류');
          $key=hash('sha256',$name."\x1f".$amount."\x1f".$memo);
          $stmt->execute([$name,$amount,$memo,$key]);if($stmt->rowCount())$added++;else $skipped++;
        }
        $pdo->commit();
      }catch(Throwable $e){$pdo->rollBack();throw $e;}finally{fclose($f);}
      $notice="가져오기 완료: {$added}건 등록 / {$skipped}건 중복 제외";
    }elseif($action==='save'){
      $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$amount=(int)($_POST['amount']??0);$memo=trim((string)($_POST['memo']??''));
      if($name===''||mb_strlen($name)>150||$amount<=0||$amount>100000000||mb_strlen($memo)>255)throw new RuntimeException('입력 내용을 확인해 주세요.');
      if($id){$st=$pdo->prepare('UPDATE cg_mother_receipts SET name=?,amount_thousand=?,memo=?,import_key=NULL WHERE id=?');$st->execute([$name,$amount,$memo,$id]);}
      else{$st=$pdo->prepare('INSERT INTO cg_mother_receipts(name,amount_thousand,memo) VALUES(?,?,?)');$st->execute([$name,$amount,$memo]);}
      header('Location: mother_receipts.php');exit;
    }elseif($action==='delete'){
      $id=(int)($_POST['id']??0);$st=$pdo->prepare('DELETE FROM cg_mother_receipts WHERE id=?');$st->execute([$id]);header('Location: mother_receipts.php');exit;
    }
  }
}catch(Throwable $e){$error=$e->getMessage();}
$q=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$limit=30;$offset=($page-1)*$limit;
$where=$q!==''?' WHERE name LIKE ? OR memo LIKE ?':'';$params=$q!==''?['%'.$q.'%','%'.$q.'%']:[];
$stats=['cnt'=>0,'total'=>0];$rows=[];$edit=null;
try{
 $st=$pdo->prepare('SELECT COUNT(*) cnt,COALESCE(SUM(amount_thousand),0) total FROM cg_mother_receipts'.$where);$st->execute($params);$stats=$st->fetch();
 $st=$pdo->prepare('SELECT * FROM cg_mother_receipts'.$where.' ORDER BY id ASC LIMIT '.$limit.' OFFSET '.$offset);$st->execute($params);$rows=$st->fetchAll();
 if(isset($_GET['edit'])){$st=$pdo->prepare('SELECT * FROM cg_mother_receipts WHERE id=?');$st->execute([(int)$_GET['edit']]);$edit=$st->fetch();}
}catch(Throwable $e){$error=$e->getMessage();}
top('어머님 경조금 수령내역');
if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';
if($notice)echo '<div class="alert alert-success">'.h($notice).'</div>';
?>
<div class="alert alert-info">수령금액은 <b>천원 단위</b>입니다. 기존 지출내역(만원 단위)과 별도로 저장됩니다. 문자발송 수신자 명단에는 자동 추가되지 않습니다.</div>
<div class="row g-3 mb-3">
 <div class="col-sm-6"><div class="card p-3"><small>검색 결과 건수</small><h3><?=number_format($stats['cnt'])?>건</h3></div></div>
 <div class="col-sm-6"><div class="card p-3"
 ><small>검색 결과 수령액</small><h3>
    <?= number_format((int)($stats['total'] ?? 0)) ?>천원
</h3><small class="text-muted"><?=number_format($stats['total']*1000)?>원</small></div></div>
</div>
<!-- <div class="alert alert-warning">
    [진단] 통계 영역 이후 PHP/HTML 출력 정상
 </div> -->
<div class="card p-3 mb-3"><h5>CSV 일괄 업로드</h5><form method="post" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="import">
<input type="file" name="csv" class="form-control" style="max-width:420px" accept=".csv" required><button class="btn btn-primary">CSV 업로드</button></form>
<small class="text-muted mt-2">열 이름: 성명 / 수령금액(천원) / 비고. 동일 내용 재업로드는 중복 제외합니다. 단, 동일한 성명·금액·비고의 실제 별도 수령 건은 중복으로 판단되므로 수동 등록해 주세요.</small></div>
<div class="card p-3 mb-3"><h5><?=$edit?'수령내역 수정':'수령내역 개별 등록'?></h5><form method="post" class="row g-2">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=h($edit['id']??0)?>">
<div class="col-md-4"><label class="form-label">성명</label><input name="name" class="form-control" required value="<?=h($edit['name']??'')?>"></div>
<div class="col-md-3"><label class="form-label">수령금액(천원)</label><input name="amount" type="number" min="1" class="form-control" required value="<?=h($edit['amount_thousand']??'')?>"></div>
<div class="col-md-5"><label class="form-label">비고</label><input name="memo" class="form-control" value="<?=h($edit['memo']??'')?>"></div>
<div class="col-12"><button class="btn btn-primary">저장</button>  

<?php if($edit):?><a href="mother_receipts.php" class="btn btn-outline-secondary">수정 취소</a><?php endif;?></div></form></div>
<div class="card p-3"><form method="get" class="d-flex gap-2 mb-3"><input name="q" class="form-control" placeholder="이름 또는 비고 검색" value="<?=h($q)?>"><button class="btn btn-outline-primary">검색</button><a href="mother_receipts.php" class="btn btn-outline-secondary">전체</a></form>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>번호</th><th>성명</th><th class="text-end">수령액(천원)</th><th>비고</th><th>관리</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=h($r['id'])?></td><td><?=h($r['name'])?></td><td class="text-end"><?=number_format($r['amount_thousand'])?></td><td><?=h($r['memo'])?></td><td><a class="btn btn-sm btn-outline-primary" href="?edit=<?=$r['id']?>">수정</a> <form class="d-inline" method="post" onsubmit="return confirm('이 내역을 삭제하시겠습니까?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger">삭제</button></form></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5" class="text-center text-muted">조회된 내역이 없습니다.</td></tr><?php endif;?></tbody></table></div>
<div class="d-flex align-items-center gap-2"><a class="btn btn-sm btn-outline-secondary <?=($page<=1?'disabled':'')?>" href="?<?=h(http_build_query(['q'=>$q,'page'=>$page-1]))?>">이전</a><span><?=$page?> / <?=max(1,(int)ceil($stats['cnt']/$limit))?></span><a class="btn btn-sm btn-outline-secondary <?=($page*$limit >= $stats['cnt']?'disabled':'')?>" href="?<?=h(http_build_query(['q'=>$q,'page'=>$page+1]))?>">다음</a></div></div>
<?php bottom(); ?>
