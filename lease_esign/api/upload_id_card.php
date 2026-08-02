<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['admin'])) {
    json_out(['ok' => false, 'message' => '관리자 로그인이 필요합니다.'], 403);
}

try {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        throw new RuntimeException('계약서 번호가 올바르지 않습니다.');
    }

    $stmt = db()->prepare('SELECT id, status, lessee_id_image FROM contracts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $contract = $stmt->fetch();

    if (!$contract || $contract['status'] !== 'completed') {
        throw new RuntimeException('서명이 완료된 계약서에서만 신분증을 첨부할 수 있습니다.');
    }

    if (!isset($_FILES['id_card'])) {
        throw new RuntimeException('신분증 이미지가 전송되지 않았습니다.');
    }

    $file = $_FILES['id_card'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('신분증 이미지 업로드에 실패했습니다.');
    }

    if (($file['size'] ?? 0) < 1 || $file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('신분증 이미지는 8MB 이하만 업로드할 수 있습니다.');
    }

    $tmp = (string)$file['tmp_name'];
    $imageInfo = @getimagesize($tmp);
    if ($imageInfo === false) {
        throw new RuntimeException('정상적인 이미지 파일이 아닙니다.');
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    $imageType = (int)($imageInfo[2] ?? 0);
    if (!isset($allowed[$imageType])) {
        throw new RuntimeException('JPG, PNG 또는 WEBP 이미지만 업로드할 수 있습니다.');
    }

    // 지나치게 큰 이미지에 대한 메모리 사용 방지
    $width = (int)($imageInfo[0] ?? 0);
    $height = (int)($imageInfo[1] ?? 0);
    if ($width < 100 || $height < 100 || $width > 12000 || $height > 12000) {
        throw new RuntimeException('이미지 크기가 허용 범위를 벗어났습니다.');
    }

    $dir = dirname(__DIR__) . '/storage/idcards';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('신분증 저장 폴더를 만들 수 없습니다.');
    }

    $name = 'lessee_id_' . $id . '_' . date('YmdHis') . '_' . token(6) . '.' . $allowed[$imageType];
    $dest = $dir . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('신분증 이미지 저장에 실패했습니다.');
    }
    @chmod($dest, 0600);

    $dbPath = 'storage/idcards/' . $name;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // 첨부 변경 시 기존 PDF를 무효화하여 신분증이 빠진 이전 PDF의 발송 방지
        $update = $pdo->prepare('UPDATE contracts SET lessee_id_image = ?, final_pdf = NULL, updated_at = NOW() WHERE id = ?');
        $update->execute([$dbPath, $id]);

        audit($id, 'admin', 'lessee_id_image_uploaded', [
            'file' => $name,
            'width' => $width,
            'height' => $height,
            'size' => (int)$file['size'],
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($dest);
        throw $e;
    }

    // 이전 첨부파일은 DB 갱신 성공 후 삭제
    $oldPath = (string)($contract['lessee_id_image'] ?? '');
    if ($oldPath !== '') {
        $oldFull = dirname(__DIR__) . '/' . ltrim($oldPath, '/');
        $realDir = realpath($dir);
        $realOld = realpath($oldFull);
        if ($realDir && $realOld && str_starts_with($realOld, $realDir . DIRECTORY_SEPARATOR)) {
            @unlink($realOld);
        }
    }

    json_out([
        'ok' => true,
        'message' => '임차인 신분증을 저장했습니다. 최종 PDF를 다시 생성해 주세요.',
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'message' => $e->getMessage()], 400);
}
