<?php
require_once __DIR__ . '/functions.php';
require_login();
$id = (int)getv('id',0); $case = ensure_case_exists($id); $pdo = get_pdo();
$expenseStmt = $pdo->prepare('SELECT * FROM expenses WHERE case_id=? ORDER BY expense_date DESC, id DESC'); $expenseStmt->execute([$id]); $expenses = $expenseStmt->fetchAll();
$docStmt = $pdo->prepare('SELECT * FROM case_documents WHERE case_id=? ORDER BY document_date DESC, id DESC'); $docStmt->execute([$id]); $documents = $docStmt->fetchAll();
$negStmt = $pdo->prepare('SELECT * FROM negotiations WHERE case_id=? ORDER BY event_date DESC, id DESC'); $negStmt->execute([$id]); $negotiations = $negStmt->fetchAll();
$images = array_values(array_filter($documents, fn($d) => (int)$d['is_image'] === 1));
$totalExpense = expense_total_for_case($id); $counts = case_summary_counts($id);
$title = '사건 상세'; page_header($title);
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 sticky-actions">
  <div>
    <h1 class="h3 mb-1"><?= e($case['title']) ?></h1>
    <div class="small-muted">사고일 <?= e($case['accident_date']) ?> · 상태 <?= e(case_status_options()[$case['status']] ?? $case['status']) ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="case_form.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">사건 수정</a>
    <a href="export_csv.php?case_id=<?= $id ?>" class="btn btn-outline-success btn-sm">CSV 다운로드</a>
    <a href="export_report_print.php?case_id=<?= $id ?>" target="_blank" class="btn btn-outline-danger btn-sm">PDF용 인쇄화면</a>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card shadow-sm rounded-4 h-100"><div class="card-body">
      <h2 class="h5">기본 정보</h2>
      <table class="table table-sm mb-0">
        <tr><th width="36%">사고 장소</th><td><?= e($case['accident_place']) ?: '-' ?></td></tr>
        <tr><th>상대방</th><td><?= e($case['opponent_name']) ?: '-' ?></td></tr>
        <tr><th>차량번호</th><td><?= e($case['vehicle_no']) ?: '-' ?></td></tr>
        <tr><th>보험사</th><td><?= e($case['opponent_insurer']) ?: '-' ?></td></tr>
        <tr><th>담당자</th><td><?= e($case['claim_handler']) ?: '-' ?></td></tr>
        <tr><th>연락처</th><td><?= e($case['handler_contact']) ?: '-' ?></td></tr>
        <tr><th>접수번호</th><td class="mono"><?= e($case['claim_no']) ?: '-' ?></td></tr>
        <tr><th>주 진료기관</th><td><?= e($case['hospital_name']) ?: '-' ?></td></tr>
        <tr><th>상해 요약</th><td><?= e($case['injury_summary']) ?: '-' ?></td></tr>
        <tr><th>예상 합의금</th><td><?= $case['expected_settlement'] !== null && $case['expected_settlement'] !== '' ? e(format_money($case['expected_settlement'])) : '-' ?></td></tr>
      </table>
      <div class="mt-3"><div class="fw-semibold mb-1">메모</div><div class="border rounded-3 bg-light p-3 preline"><?= e($case['notes']) ?: '-' ?></div></div>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="row g-3">
      <div class="col-md-3"><div class="card shadow-sm rounded-4"><div class="card-body"><div class="small-muted">총 지출액</div><div class="fs-5 fw-bold"><?= e(format_money($totalExpense)) ?></div></div></div></div>
      <div class="col-md-3"><div class="card shadow-sm rounded-4"><div class="card-body"><div class="small-muted">보관 문서</div><div class="fs-5 fw-bold"><?= e($counts['documents']) ?>건</div></div></div></div>
      <div class="col-md-3"><div class="card shadow-sm rounded-4"><div class="card-body"><div class="small-muted">사진</div><div class="fs-5 fw-bold"><?= e($counts['images']) ?>장</div></div></div></div>
      <div class="col-md-3"><div class="card shadow-sm rounded-4"><div class="card-body"><div class="small-muted">협의 이력</div><div class="fs-5 fw-bold"><?= e($counts['negotiations']) ?>건</div></div></div></div>
    </div>
    <div class="card shadow-sm rounded-4 mt-3"><div class="card-body">
      <h2 class="h5">권장 보관 항목</h2>
      <ul class="mb-0">
        <li>진료비 영수증, 처방전, 진단서, 통원확인서</li>
        <li>보험사 문자/이메일/우편 문서, 제안금 내역</li>
        <li>사고 현장 사진, 차량/상해 사진, 수리 견적서</li>
        <li>통화일시와 협상 포인트를 협의이력에 남기기</li>
      </ul>
    </div></div>
  </div>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm rounded-4 mb-4"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">지출 내역</h2><a href="expense_form.php?case_id=<?= $id ?>" class="btn btn-sm btn-primary">+ 지출 등록</a></div>
      <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>일자</th><th>분류</th><th>내용</th><th class="text-end">금액</th><th></th></tr></thead><tbody>
      <?php if(!$expenses): ?><tr><td colspan="5" class="text-center text-muted py-4">등록된 지출이 없습니다.</td></tr><?php endif; ?>
      <?php foreach($expenses as $exp): ?><tr><td><?= e($exp['expense_date']) ?></td><td><?= e(expense_type_options()[$exp['expense_type']] ?? $exp['expense_type']) ?></td><td><div><?= e($exp['description']) ?></div><div class="small-muted"><?= e($exp['memo']) ?></div></td><td class="text-end"><?= e(format_money($exp['amount'])) ?></td><td class="text-end"><a href="expense_form.php?case_id=<?= $id ?>&id=<?= (int)$exp['id'] ?>" class="btn btn-sm btn-outline-secondary">수정</a></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div></div>

    <div class="card shadow-sm rounded-4"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">보험사 제안금 / 합의이력</h2><a href="negotiation_form.php?case_id=<?= $id ?>" class="btn btn-sm btn-primary">+ 이력 등록</a></div>
      <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>일자</th><th>구분</th><th>요약</th><th class="text-end">금액</th><th></th></tr></thead><tbody>
      <?php if(!$negotiations): ?><tr><td colspan="5" class="text-center text-muted py-4">등록된 협의 이력이 없습니다.</td></tr><?php endif; ?>
      <?php foreach($negotiations as $n): ?><tr><td><?= e($n['event_date']) ?></td><td><?= e(offer_stage_options()[$n['stage']] ?? $n['stage']) ?></td><td><div><?= e($n['summary']) ?></div><div class="small-muted"><?= e($n['channel']) ?> / <?= e($n['counterparty']) ?></div></td><td class="text-end"><?= $n['amount'] !== null && $n['amount'] !== '' ? e(format_money($n['amount'])) : '-' ?></td><td class="text-end"><a href="negotiation_form.php?case_id=<?= $id ?>&id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-secondary">수정</a></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm rounded-4 mb-3"><div class="card-body">
      <h2 class="h5">문서/사진 업로드</h2>
      <form method="post" action="document_upload.php" enctype="multipart/form-data" class="row g-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="case_id" value="<?= $id ?>">
        <div class="col-md-6"><select name="document_type" class="form-select" required><?php foreach(document_type_options() as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><input type="date" name="document_date" class="form-control" value="<?= e(today()) ?>"></div>
        <div class="col-12"><input type="text" name="title" class="form-control" placeholder="문서 제목 예: 4월 2일 병원 영수증 / 보험사 제안서" required></div>
        <div class="col-12"><textarea name="memo" class="form-control" rows="2" placeholder="간단 메모"></textarea></div>
        <div class="col-12"><input type="file" name="upload_files[]" class="form-control" multiple required><div class="small-muted mt-1">여러 장 사진/파일 업로드 가능. 허용: jpg, png, webp, pdf, docx, xlsx, hwp 등. 파일당 최대 20MB.</div></div>
        <div class="col-12 d-grid"><button class="btn btn-primary">저장</button></div>
      </form>
    </div></div>

    <div class="card shadow-sm rounded-4 mb-3"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">사진 미리보기</h2><div class="small-muted">총 <?= count($images) ?>장</div></div>
      <?php if(!$images): ?><div class="text-muted">등록된 사진이 없습니다.</div><?php else: ?><div class="d-flex gap-2 flex-wrap"><?php foreach($images as $img): ?><a href="document_download.php?id=<?= (int)$img['id'] ?>" title="<?= e($img['title']) ?>"><img src="<?= e('uploads/' . $img['storage_group'] . '/' . $img['stored_name']) ?>" class="thumb" alt=""></a><?php endforeach; ?></div><?php endif; ?>
    </div></div>

    <div class="card shadow-sm rounded-4"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">보관 문서</h2><div class="small-muted">총 <?= count($documents) ?>건</div></div>
      <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>일자</th><th>분류</th><th>제목</th><th>파일</th><th></th></tr></thead><tbody>
      <?php if(!$documents): ?><tr><td colspan="5" class="text-center text-muted py-4">업로드된 문서가 없습니다.</td></tr><?php endif; ?>
      <?php foreach($documents as $doc): ?><tr><td><?= e($doc['document_date']) ?></td><td><?= e(document_type_options()[$doc['document_type']] ?? $doc['document_type']) ?></td><td><div><?= e($doc['title']) ?></div><div class="small-muted"><?= e($doc['memo']) ?></div></td><td><?= e($doc['original_name']) ?></td><td class="text-end"><a href="document_download.php?id=<?= (int)$doc['id'] ?>" class="btn btn-sm btn-outline-primary">다운로드</a><form method="post" action="document_delete.php" class="d-inline" onsubmit="return confirm('삭제하시겠습니까?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><input type="hidden" name="case_id" value="<?= $id ?>"><button class="btn btn-sm btn-outline-danger">삭제</button></form></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div></div>
  </div>
</div>
<?php page_footer(); ?>
