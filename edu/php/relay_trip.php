<?php
require_once __DIR__ . '/lib/ppurio_token_manager.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// ✅ 입력 데이터 수신
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["error" => "Invalid data"]);
    exit;
}

$name = $data["name"] ?? "";
$rawContact = $data["contact"] ?? "";
$attendees = $data["attendees"] ?? "";
$comment = $data["comment"] ?? "-";

// ✅ 연락처 정제
$contact = preg_replace('/[^0-9]/', '', $rawContact);
if (strlen($contact) === 10) {
    $contact = '0' . $contact;
}
if (strlen($contact) !== 11) {
    echo json_encode(["error" => "잘못된 연락처 형식입니다"]);
    exit;
}

// ✅ 토큰 발급
$token = getPpurioToken();
if (!$token) {
    echo json_encode(["error" => "토큰 발급 실패"]);
    exit;
}

// ✅ 알림톡 전송 payload 구성
$payload = [
    "accountId" => "ajou9770",
    "templateCode" => "ppur_2025071615063126394257542",
    "messages" => [[
        "to" => $contact,
        "name" => $name,
        "changeWord" => [
            "var1" => $contact,
            "var2" => $attendees, // ✅ 콤마 누락 주의!
            "var3" => $comment
        ]
    ]]
];

// ✅ HTTP 요청 옵션 설정
$options = [
    "http" => [
        "method"  => "POST",
        "header"  => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
        "content" => json_encode($payload),
        "ignore_errors" => true
    ]
];

// ✅ 요청 및 응답 처리
$context = stream_context_create($options);
$response = file_get_contents("https://message.ppurio.com/v1/send/alimtalk", false, $context);

// ✅ 응답 검사 및 반환
if ($response === false) {
    echo json_encode(["error" => "전송 실패 (file_get_contents 오류)"]);
    exit;
}

// ✅ 응답을 JSON으로 반환
echo $response;
