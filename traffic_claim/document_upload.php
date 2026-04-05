<?php
require_once __DIR__ . '/functions.php';
require_login(); verify_csrf();
$pdo = get_pdo();
$caseId = (int)post('case_id',0); ensure_case_exists($caseId);
$documentType = trim((string)post('document_type','etc'));
$documentDate = trim((string)post('document_date',today()));
$title = trim((string)post('title'));
$memo = trim((string)post('memo'));
$files = normalize_files_array($_FILES['upload_files'] ?? []);
if (!$files) { set_flash('danger', '업로드할 파일을 선택해 주세요.'); redirect('case_view.php?id=' . $caseId); }
$inserted = 0;
foreach ($files as $idx => $file) {
    $meta = upload_single_file($file, 'documents');
    $rowTitle = $title !== '' ? $title . (count($files) > 1 ? ' #' . ($idx + 1) : '') : $meta['original_name'];
    $pdo->prepare('INSERT INTO case_documents (case_id, document_type, title, memo, document_date, original_name, stored_name, mime_type, file_ext, file_size, file_path, storage_group, is_image, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$caseId, $documentType, $rowTitle, $memo, $documentDate ?: null, $meta['original_name'], $meta['stored_name'], $meta['mime_type'], $meta['file_ext'], $meta['file_size'], $meta['file_path'], $meta['storage_group'], $meta['is_image'], now(), now()]);
    $inserted++;
}
set_flash('success', $inserted . '건의 문서를 저장했습니다.');
redirect('case_view.php?id=' . $caseId);
