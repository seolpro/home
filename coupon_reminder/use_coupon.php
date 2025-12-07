<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo "<script>alert('잘못된 요청입니다.'); history.back();</script>";
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE coupons SET used_flag = 1, used_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo "<script>alert('사용완료로 등록되었습니다. 앞으로 알림톡 발송 대상에서 제외됩니다.'); location.href='index.php';</script>";
} catch (Exception $e) {
    error_log('use_coupon error: ' . $e->getMessage());
    echo "<script>alert('처리 중 오류가 발생했습니다.'); history.back();</script>";
}
