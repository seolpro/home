<?php
declare(strict_types=1);

function evidence_upload_directory(): string
{
    return dirname(__DIR__) . '/uploads/evidence';
}

function evidence_max_bytes(): int
{
    return max(1, (int)setting('evidence_max_mb', '10')) * 1024 * 1024;
}

function evidence_allowed_mime_types(): array
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];
}

/** @return array{path:string,name:string,mime:string,size:int}|null */
function save_evidence_upload(array $file, bool $required): ?array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new RuntimeException('선택한 휴가유형은 증빙서류 첨부가 필수입니다.');
        }
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => '서버에서 허용한 파일 크기를 초과했습니다.',
            UPLOAD_ERR_FORM_SIZE => '허용한 파일 크기를 초과했습니다.',
            UPLOAD_ERR_PARTIAL => '파일이 일부만 업로드되었습니다.',
            UPLOAD_ERR_NO_TMP_DIR => '서버의 임시 폴더가 없습니다.',
            UPLOAD_ERR_CANT_WRITE => '서버에 파일을 저장할 수 없습니다.',
            UPLOAD_ERR_EXTENSION => '서버 확장 기능에서 업로드를 중단했습니다.',
        ];
        throw new RuntimeException($messages[$error] ?? '증빙서류 업로드 중 오류가 발생했습니다.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    $originalName = trim((string)($file['name'] ?? ''));

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('정상적인 업로드 파일이 아닙니다.');
    }
    if ($size < 1 || $size > evidence_max_bytes()) {
        throw new RuntimeException('증빙서류는 최대 ' . number_format(evidence_max_bytes() / 1024 / 1024) . 'MB까지 업로드할 수 있습니다.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = evidence_allowed_mime_types();
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('증빙서류는 PDF, JPG, PNG, WEBP, HEIC 파일만 업로드할 수 있습니다.');
    }

    $dir = evidence_upload_directory();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('증빙서류 저장 폴더를 생성할 수 없습니다.');
    }

    $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $storedName;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('증빙서류를 서버에 저장하지 못했습니다.');
    }
    @chmod($target, 0644);

    return [
        'path' => 'uploads/evidence/' . $storedName,
        'name' => mb_substr($originalName, 0, 255, 'UTF-8'),
        'mime' => $mime,
        'size' => $size,
    ];
}

function remove_evidence_file(?array $evidence): void
{
    if (!$evidence || empty($evidence['path'])) return;
    $base = realpath(evidence_upload_directory());
    $file = realpath(dirname(__DIR__) . '/' . ltrim((string)$evidence['path'], '/'));
    if ($base && $file && str_starts_with($file, $base . DIRECTORY_SEPARATOR)) @unlink($file);
}
