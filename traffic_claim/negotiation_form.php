<?php
require_once __DIR__ . '/functions.php';
require_login();
$caseId = (int)getv('case_id', 0); $case = ensure_case_exists($caseId);
$id = (int)getv('id', 0);
$row = ['event_date'=>today(),'stage'=>'insurer_offer','amount'=>'','channel'=>'전화','counterparty'=>'보험사 담당자','summary'=>'','details'=>''];
if ($id > 0) {
  $st = get_pdo()->prepare('SELECT * FROM negotiations WHERE id=? AND case_id=?'); $st->execute([$id,$caseId]);
  $found = $st->fetch(); if ($found) $row = $found;
}
$title = $id ? '협의/합의이력 수정' : '협의/합의이력 등록'; page_header($title);
?>
<h1 class="h4 mb-3"><?= e($title) ?> · <?= e($case['title']) ?></h1>
<div class="card rounded-4 shadow-sm"><div class="card-body">
<form method="post" action="negotiation_save.php" class="row g-3">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="case_id" value="<?= $caseId ?>">
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="col-md-3"><label class="form-label">일자</label><input type="date" name="event_date" class="form-control" value="<?= e($row['event_date']) ?>" required></div>
  <div class="col-md-3"><label class="form-label">구분</label><select name="stage" class="form-select"><?php foreach(offer_stage_options() as $k=>$v): ?><option value="<?= e($k) ?>" <?= $row['stage']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">금액</label><input type="number" name="amount" class="form-control" value="<?= e((string)$row['amount']) ?>" min="0" step="1"></div>
  <div class="col-md-3"><label class="form-label">채널</label><input type="text" name="channel" class="form-control" value="<?= e($row['channel']) ?>" placeholder="전화/문자/이메일/대면"></div>
  <div class="col-md-4"><label class="form-label">상대</label><input type="text" name="counterparty" class="form-control" value="<?= e($row['counterparty']) ?>"></div>
  <div class="col-md-8"><label class="form-label">요약</label><input type="text" name="summary" class="form-control" value="<?= e($row['summary']) ?>" placeholder="예: 보험사 350만원 제안, 추가 진단서 요청"></div>
  <div class="col-12"><label class="form-label">상세 메모</label><textarea name="details" class="form-control" rows="6" placeholder="통화 내용, 협의 포인트, 다음 액션 등을 자세히 기록"><?= e($row['details']) ?></textarea></div>
  <div class="col-12 d-flex gap-2"><button class="btn btn-primary">저장</button><a href="case_view.php?id=<?= $caseId ?>" class="btn btn-outline-secondary">돌아가기</a></div>
</form>
<?php if ($id>0): ?><hr><form method="post" action="negotiation_delete.php" onsubmit="return confirm('삭제하시겠습니까?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="case_id" value="<?= $caseId ?>"><button class="btn btn-outline-danger">삭제</button></form><?php endif; ?>
</div></div>
<?php page_footer(); ?>
