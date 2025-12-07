<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$title        = trim($_POST['title'] ?? '');
$receiver_tel = only_digits($_POST['receiver_tel'] ?? '');
$barcode      = trim($_POST['barcode'] ?? '');
$coupon_code  = trim($_POST['coupon_code'] ?? '');
$expire_date  = trim($_POST['expire_date'] ?? '');

if ($title === '' || $receiver_tel === '' || $expire_date === '') {
    echo "<script>alert('필수 항목이 누락되었습니다.'); history.back();</script>";
    exit;
}

// 이미지 업로드 처리
$image_path = null;
if (!empty($_FILES['image']['name'])) {
    if (!is_dir(COUPON_UPLOAD_DIR)) {
        mkdir(COUPON_UPLOAD_DIR, 0755, true);
    }
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $fname = 'coupon_' . date('YmdHis') . '_' . mt_rand(1000,9999) . '.' . $ext;
    $dest = COUPON_UPLOAD_DIR . '/' . $fname;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
        $image_path = $dest;
    }
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO coupons (title, barcode, coupon_code, expire_date, receiver_tel, image_path)
        VALUES (:title, :barcode, :coupon_code, :expire_date, :receiver_tel, :image_path)
    ");
    $stmt->execute([
        ':title'        => $title,
        ':barcode'      => $barcode ?: null,
        ':coupon_code'  => $coupon_code ?: null,
        ':expire_date'  => $expire_date,
        ':receiver_tel' => $receiver_tel,
        ':image_path'   => $image_path
    ]);

    echo "<script>alert('쿠폰이 등록되었습니다.'); location.href='index.php';</script>";
} catch (Exception $e) {
    error_log('save_coupon error: ' . $e->getMessage());
    echo "<script>alert('저장 중 오류가 발생했습니다.'); history.back();</script>";
}
