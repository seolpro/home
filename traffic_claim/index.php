<?php
require_once __DIR__ . '/functions.php';
require_login();
$pdo = get_pdo();
$q = trim((string)getv('q', ''));
$status = trim((string)getv('status', ''));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(title LIKE ? OR accident_place LIKE ? OR opponent_insurer LIKE ? OR opponent_name LIKE ? OR claim_no LIKE ?)';
    for ($i=0;$i<5;$i++) $params[] = '%' . $q . '%';
}
if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
$sql = 'SELECT * FROM accident_cases';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY accident_date DESC, id DESC';
$st = $pdo->prepare($sql); $st->execute($params); $cases = $st->fetchAll();
$title = APP_NAME;
page_header($title);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3 mb-1">사건 목록</h1>
    <div class="small-muted">증빙, 보험사 제안금, 합의이력을 한 곳에서 관리</div>
  </div>
</div>
<div class="card rounded-4 shadow-sm mb-4"><div class="card-body">
  <form class="row g-2">
    <div class="col-md-6"><input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="사건명, 장소, 보험사, 상대방, 접수번호 검색"></div>
    <div class="col-md-3"><select name="status" class="form-select"><option value="">전체 상태</option><?php foreach(case_status_options() as $k=>$v): ?><option value="<?= e($k) ?>" <?= $status===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">조회</button></div>
  </form>
</div></div>
<div class="row g-3">
<?php if (!$cases): ?>
  <div class="col-12"><div class="alert alert-light border">등록된 사건이 없습니다.</div></div>
<?php endif; ?>
<?php foreach ($cases as $case): $counts = case_summary_counts((int)$case['id']); ?>
  <div class="col-lg-6">
    <div class="card rounded-4 shadow-sm h-100"><div class="card-body">
      <div class="d-flex justify-content-between gap-3">
        <div>
          <h2 class="h5 mb-1"><a href="case_view.php?id=<?= (int)$case['id'] ?>" class="text-decoration-none"><?= e($case['title']) ?></a></h2>
          <div class="small-muted">사고일 <?= e($case['accident_date']) ?> · 상태 <?= e(case_status_options()[$case['status']] ?? $case['status']) ?></div>
          <div class="small-muted">보험사 <?= e($case['opponent_insurer']) ?> / 담당 <?= e($case['claim_handler']) ?></div>
          <div class="small-muted">접수번호 <?= e($case['claim_no']) ?: '-' ?></div>
        </div>
        <div class="text-end">
          <div class="badge text-bg-light border"><?= e($counts['documents']) ?> 문서</div>
          <div class="badge text-bg-light border"><?= e($counts['images']) ?> 사진</div>
          <div class="badge text-bg-light border"><?= e($counts['negotiations']) ?> 협의</div>
        </div>
      </div>
      <hr>
      <div class="row text-center g-2">
        <div class="col-4"><div class="border rounded-3 p-2"><div class="small-muted">총 지출</div><div class="fw-bold"><?= e(format_money(expense_total_for_case((int)$case['id']))) ?></div></div></div>
        <div class="col-4"><div class="border rounded-3 p-2"><div class="small-muted">최근 제안금</div><div class="fw-bold"><?= latest_offer_amount((int)$case['id']) !== null ? e(format_money((int)latest_offer_amount((int)$case['id']))) : '-' ?></div></div></div>
        <div class="col-4"><div class="border rounded-3 p-2"><div class="small-muted">예상 합의금</div><div class="fw-bold"><?= $case['expected_settlement'] !== null && $case['expected_settlement'] !== '' ? e(format_money($case['expected_settlement'])) : '-' ?></div></div></div>
      </div>
      <div class="mt-3 d-flex gap-2 flex-wrap">
        <a href="case_view.php?id=<?= (int)$case['id'] ?>" class="btn btn-primary btn-sm">상세보기</a>
        <a href="case_form.php?id=<?= (int)$case['id'] ?>" class="btn btn-outline-secondary btn-sm">수정</a>
      </div>
    </div></div>
  </div>
<?php endforeach; ?>
</div>
<?php page_footer(); ?>
