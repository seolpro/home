<?php
require_once __DIR__ . '/functions.php';
require_login();
$id = (int)getv('id', 0);
$case = $id ? ensure_case_exists($id) : [
  'title'=>'','accident_date'=>today(),'accident_place'=>'','opponent_name'=>'','opponent_insurer'=>'','claim_handler'=>'','handler_contact'=>'',
  'claim_no'=>'','vehicle_no'=>'','hospital_name'=>'','injury_summary'=>'','expected_settlement'=>'','status'=>'collecting','notes'=>''
];
$title = $id ? '사건 수정' : '사건 추가';
page_header($title);
?>
<h1 class="h3 mb-3"><?= e($title) ?></h1>
<div class="card rounded-4 shadow-sm"><div class="card-body">
<form method="post" action="case_save.php" class="row g-3">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="col-md-8"><label class="form-label">사건명</label><input type="text" name="title" class="form-control" value="<?= e($case['title']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">사고일</label><input type="date" name="accident_date" class="form-control" value="<?= e($case['accident_date']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">사고 장소</label><input type="text" name="accident_place" class="form-control" value="<?= e($case['accident_place']) ?>"></div>
  <div class="col-md-3"><label class="form-label">상대방 이름</label><input type="text" name="opponent_name" class="form-control" value="<?= e($case['opponent_name']) ?>"></div>
  <div class="col-md-3"><label class="form-label">차량번호/식별</label><input type="text" name="vehicle_no" class="form-control" value="<?= e($case['vehicle_no']) ?>"></div>
  <div class="col-md-4"><label class="form-label">상대 보험사</label><input type="text" name="opponent_insurer" class="form-control" value="<?= e($case['opponent_insurer']) ?>"></div>
  <div class="col-md-4"><label class="form-label">보험사 담당자</label><input type="text" name="claim_handler" class="form-control" value="<?= e($case['claim_handler']) ?>"></div>
  <div class="col-md-4"><label class="form-label">담당자 연락처</label><input type="text" name="handler_contact" class="form-control" value="<?= e($case['handler_contact']) ?>"></div>
  <div class="col-md-4"><label class="form-label">접수번호/사고번호</label><input type="text" name="claim_no" class="form-control mono" value="<?= e($case['claim_no']) ?>"></div>
  <div class="col-md-4"><label class="form-label">주 진료기관</label><input type="text" name="hospital_name" class="form-control" value="<?= e($case['hospital_name']) ?>"></div>
  <div class="col-md-4"><label class="form-label">예상 합의금</label><input type="number" name="expected_settlement" class="form-control" value="<?= e((string)$case['expected_settlement']) ?>" min="0" step="1"></div>
  <div class="col-md-4"><label class="form-label">진행 상태</label><select name="status" class="form-select"><?php foreach(case_status_options() as $k=>$v): ?><option value="<?= e($k) ?>" <?= $case['status']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-8"><label class="form-label">상해/치료 요약</label><input type="text" name="injury_summary" class="form-control" value="<?= e($case['injury_summary']) ?>" placeholder="예: 경추염좌, 3주 진단, 통원치료 중"></div>
  <div class="col-12"><label class="form-label">메모</label><textarea name="notes" rows="5" class="form-control" placeholder="사고 경위, 증빙 누락 메모, 보험사 대응 포인트 등"><?= e($case['notes']) ?></textarea></div>
  <div class="col-12 d-flex gap-2"><button class="btn btn-primary">저장</button><a href="<?= $id ? 'case_view.php?id=' . $id : 'index.php' ?>" class="btn btn-outline-secondary">취소</a></div>
</form>
</div></div>
<?php page_footer(); ?>
