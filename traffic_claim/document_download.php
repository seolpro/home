<?php
require_once __DIR__ . '/functions.php';
require_login();
$id = (int)getv('id',0);
$st = get_pdo()->prepare('SELECT * FROM case_documents WHERE id=?'); $st->execute([$id]);
$doc = $st->fetch(); if (!$doc) { http_response_code(404); exit('문서를 찾을 수 없습니다.'); }
if (!is_file($doc['file_path'])) { http_response_code(404); exit('파일이 존재하지 않습니다.'); }
header('Content-Description: File Transfer');
header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_name']) . '"');
header('Content-Length: ' . filesize($doc['file_path']));
readfile($doc['file_path']);
exit;
