<?php
require_once __DIR__ . '/functions.php';
require_login(); verify_csrf();
$pdo = get_pdo();
$caseId = (int)post('case_id',0); ensure_case_exists($caseId);
$id = (int)post('id',0);
$vals = [trim((string)post('event_date')), trim((string)post('stage')), post('amount') !== '' ? (int)post('amount') : null, trim((string)post('channel')), trim((string)post('counterparty')), trim((string)post('summary')), trim((string)post('details')), now()];
if ($id > 0) {
  $pdo->prepare('UPDATE negotiations SET event_date=?, stage=?, amount=?, channel=?, counterparty=?, summary=?, details=?, updated_at=? WHERE id=? AND case_id=?')->execute(array_merge($vals, [$id,$caseId]));
  set_flash('success', '협의/합의이력이 수정되었습니다.');
} else {
  $pdo->prepare('INSERT INTO negotiations (event_date, stage, amount, channel, counterparty, summary, details, created_at, updated_at, case_id) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([trim((string)post('event_date')), trim((string)post('stage')), post('amount') !== '' ? (int)post('amount') : null, trim((string)post('channel')), trim((string)post('counterparty')), trim((string)post('summary')), trim((string)post('details')), now(), now(), $caseId]);
  set_flash('success', '협의/합의이력이 등록되었습니다.');
}
redirect('case_view.php?id=' . $caseId);
