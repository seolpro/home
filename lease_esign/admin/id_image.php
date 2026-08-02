<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib.php';
admin_required();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT lessee_id_image FROM contracts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();

$relative = (string)($row['lessee_id_image'] ?? '');
if ($relative === '') {
    http_response_code(404);
    exit('신분증 이미지가 없습니다.');
}

$baseDir = realpath(dirname(__DIR__) . '/storage/idcards');
$file = realpath(dirname(__DIR__) . '/' . ltrim($relative, '/'));

if (!$baseDir || !$file || !str_starts_with($file, $baseDir . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('신분증 이미지 파일을 찾을 수 없습니다.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('지원하지 않는 이미지입니다.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($file);
