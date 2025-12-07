<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ppurio_coupon_lib.php';

$pdo = getDB();
$today = new DateTime('today');

$sql = "SELECT * FROM coupons WHERE used_flag = 0";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $exp = new DateTime($row['expire_date']);
    $diff = (int)$today->diff($exp)->format('%r%a'); // 남은 일수

    // 이미 만료되었거나 오늘 이전이면 패스
    if ($diff < 0) continue;

    $needStage = null;
    $fieldName = null;

    if ($diff === 10 && empty($row['remind10_sent_at'])) {
        $needStage = 10;
        $fieldName = 'remind10_sent_at';
    } elseif ($diff === 5 && empty($row['remind5_sent_at'])) {
        $needStage = 5;
        $fieldName = 'remind5_sent_at';
    } elseif ($diff === 2 && empty($row['remind2_sent_at'])) {
        $needStage = 2;
        $fieldName = 'remind2_sent_at';
    }

    if (!$needStage) continue;

    $tel = only_digits($row['receiver_tel']);
    if (strlen($tel) < 10) continue; // 전화번호 이상할 때는 패스

    // 템플릿 변수 (예시)
    $couponName = $row['title'];
    $expireDate = $row['expire_date'];
    $remainDays = $needStage;
    $issuerInfo = '아주대의료원신협'; // 필요시 변경

    $variables = [$couponName, $expireDate, (string)$remainDays, $issuerInfo];

    // 메시지 본문(템플릿에 맞게 간단히)
    $msg = "[쿠폰 유효기간 안내]\n"
         . "{$couponName} 쿠폰의 유효기간이 D-{$remainDays} 입니다.\n"
         . "유효기간: {$expireDate}\n"
         . "기간 내 꼭 사용해 주세요.\n"
         . "- {$issuerInfo} -";

    $res = coupon_sendAlimtalk($tel, $msg, $variables);

    if ($res['ok']) {
        $update = $pdo->prepare("UPDATE coupons SET {$fieldName} = NOW() WHERE id = :id");
        $update->execute([':id' => $row['id']]);
    } else {
        error_log('coupon reminder send error: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
    }
}
