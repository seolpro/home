<?php
require_once __DIR__ . '/functions.php';
require_login(); verify_csrf();
$caseId = (int)post('case_id',0); $id = (int)post('id',0);
get_pdo()->prepare('DELETE FROM negotiations WHERE id=? AND case_id=?')->execute([$id,$caseId]);
set_flash('success', '협의/합의이력이 삭제되었습니다.');
redirect('case_view.php?id=' . $caseId);
