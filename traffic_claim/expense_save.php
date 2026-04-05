<?php
require_once __DIR__ . '/functions.php';
require_login(); verify_csrf();
$pdo = get_pdo();
$caseId = (int)post('case_id',0); ensure_case_exists($caseId);
$id = (int)post('id',0);
$params = [trim((string)post('expense_date')), trim((string)post('expense_type')), trim((string)post('description')), (int)post('amount',0), trim((string)post('memo')), now()];
if ($id > 0) {
  $pdo->prepare('UPDATE expenses SET expense_date=?, expense_type=?, description=?, amount=?, memo=?, updated_at=? WHERE id=? AND case_id=?')->execute(array_merge($params, [$id,$caseId]));
  set_flash('success', '지출이 수정되었습니다.');
} else {
  $pdo->prepare('INSERT INTO expenses (expense_date, expense_type, description, amount, memo, created_at, updated_at, case_id) VALUES (?,?,?,?,?,?,?,?)')->execute([trim((string)post('expense_date')), trim((string)post('expense_type')), trim((string)post('description')), (int)post('amount',0), trim((string)post('memo')), now(), now(), $caseId]);
  set_flash('success', '지출이 등록되었습니다.');
}
redirect('case_view.php?id=' . $caseId);
