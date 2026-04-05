<?php
require_once __DIR__ . '/functions.php';
require_login(); verify_csrf();
$id = (int)post('id',0); $caseId = (int)post('case_id',0);
$st = get_pdo()->prepare('SELECT * FROM case_documents WHERE id=? AND case_id=?'); $st->execute([$id,$caseId]);
$doc = $st->fetch();
if ($doc) {
    get_pdo()->prepare('DELETE FROM case_documents WHERE id=? AND case_id=?')->execute([$id,$caseId]);
    delete_physical_file($doc['file_path']);
}
set_flash('success', '문서를 삭제했습니다.');
redirect('case_view.php?id=' . $caseId);
