<?php
// /www/coupon_reminder/delete_coupon.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// JSON 응답 헬퍼
function json_response($data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'msg' => 'POST 요청만 허용됩니다.'], 405);
}

// id 파라미터 확인
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    json_response(['ok' => false, 'msg' => '잘못된 요청입니다. (id 누락)'], 400);
}

try {
    $pdo = getDB();

    // (선택) 이미지 파일도 같이 지우고 싶다면 먼저 경로 조회
    $stmt = $pdo->prepare("SELECT image_path FROM coupons WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // DB에서 쿠폰 삭제
    $del = $pdo->prepare("DELETE FROM coupons WHERE id = :id LIMIT 1");
    $del->execute([':id' => $id]);

    if ($del->rowCount() > 0) {
        // (선택) 이미지 파일 삭제
        if (!empty($row['image_path']) && file_exists($row['image_path'])) {
            @unlink($row['image_path']);
        }

        json_response(['ok' => true]);
    } else {
        json_response(['ok' => false, 'msg' => '이미 삭제되었거나 존재하지 않는 쿠폰입니다.'], 404);
    }

} catch (Exception $e) {
    error_log('DELETE_COUPON_ERROR: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => '삭제 중 오류가 발생했습니다.'], 500);
}
