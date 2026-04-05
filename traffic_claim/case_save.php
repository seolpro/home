<?php
require_once __DIR__ . '/functions.php';
require_login(); verify_csrf();
$pdo = get_pdo();
$id = (int)post('id', 0);
$data = [
  trim((string)post('title')),
  trim((string)post('accident_date')),
  trim((string)post('accident_place')),
  trim((string)post('opponent_name')),
  trim((string)post('opponent_insurer')),
  trim((string)post('claim_handler')),
  trim((string)post('handler_contact')),
  trim((string)post('claim_no')),
  trim((string)post('vehicle_no')),
  trim((string)post('hospital_name')),
  trim((string)post('injury_summary')),
  post('expected_settlement') !== '' ? (int)post('expected_settlement') : null,
  trim((string)post('status')),
  trim((string)post('notes')),
];
if ($data[0] === '' || $data[1] === '') { set_flash('danger', '사건명과 사고일은 필수입니다.'); redirect($id ? 'case_form.php?id=' . $id : 'case_form.php'); }
if ($id > 0) {
  $sql = 'UPDATE accident_cases SET title=?, accident_date=?, accident_place=?, opponent_name=?, opponent_insurer=?, claim_handler=?, handler_contact=?, claim_no=?, vehicle_no=?, hospital_name=?, injury_summary=?, expected_settlement=?, status=?, notes=?, updated_at=? WHERE id=?';
  $params = array_merge($data, [now(), $id]);
  $pdo->prepare($sql)->execute($params);
  set_flash('success', '사건이 수정되었습니다.');
  redirect('case_view.php?id=' . $id);
}
$sql = 'INSERT INTO accident_cases (title, accident_date, accident_place, opponent_name, opponent_insurer, claim_handler, handler_contact, claim_no, vehicle_no, hospital_name, injury_summary, expected_settlement, status, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
$params = array_merge($data, [now(), now()]);
$pdo->prepare($sql)->execute($params);
$newId = (int)$pdo->lastInsertId();
set_flash('success', '사건이 등록되었습니다.');
redirect('case_view.php?id=' . $newId);
