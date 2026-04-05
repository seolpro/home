<?php
require_once __DIR__ . '/functions.php';
require_login();
$caseId = (int)getv('case_id', 0); $case = ensure_case_exists($caseId);
$id = (int)getv('id', 0);
$expense = ['expense_date'=>today(),'expense_type'=>'medical','description'=>'','amount'=>'','memo'=>''];
if ($id > 0) {
  $st = get_pdo()->prepare('SELECT * FROM expenses WHERE id=? AND case_id=?'); $st->execute([$id,$caseId]);
  $row = $st->fetch(); if ($row) $expense = $row;
}
$title = $id ? '지출 수정' : '지출 등록'; page_header($title);
?>
<h1 class="h4 mb-3"><?= e($title) ?> · <?= e($case['title']) ?></h1>
<div class="card rounded-4 shadow-sm"><div class="card-body">
<form method="post" action="expense_save.php" class="row g-3">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="case_id" value="<?= $caseId ?>">
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="col-md-3"><label class="form-label">일자</label><input type="date" name="expense_date" class="form-control" value="<?= e($expense['expense_date']) ?>" required></div>
  <div class="col-md-3"><label class="form-label">분류</label><select name="expense_type" class="form-select"><?php foreach(expense_type_options() as $k=>$v): ?><option value="<?= e($k) ?>" <?= $expense['expense_type']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><label class="form-label">내용</label><input type="text" name="description" class="form-control" value="<?= e($expense['description']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">금액</label><input type="number" name="amount" class="form-control" value="<?= e((string)$expense['amount']) ?>" min="0" step="1" required></div>
  <div class="col-12"><label class="form-label">메모</label><textarea name="memo" class="form-control" rows="4"><?= e($expense['memo']) ?></textarea></div>
  <div class="col-12 d-flex gap-2"><button class="btn btn-primary">저장</button><a href="case_view.php?id=<?= $caseId ?>" class="btn btn-outline-secondary">돌아가기</a></div>
</form>
</div></div>
<?php page_footer(); ?>
