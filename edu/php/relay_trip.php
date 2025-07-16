<?php
require_once __DIR__ . '/lib/ppurio_token_manager.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["error" => "Invalid data"]);
    exit;
}

$name = $data["name"] ?? "";
$contact = $data["contact"] ?? "";
$attendees = $data["attendees"] ?? "";
$comment = $data["comment"] ?? "-";

// ✅ 토큰 발급
$token = getPpurioToken();
if (!$token) {
    echo json_encode(["error" => "토큰 발급 실패"]);
    exit;
}

// 📩 알림톡 전송
$payload = [
    "accountId" => "ajou9770",
    "templateCode" => "ppur_2025071615063126394257542",
    "messages" => [[
        "to" => $contact,
        "name" => $name,
        "changeWord" => [
            "var1" => $contact,
            "var2" => $attendees . "명"
            "var3" => $comment
        ]
    ]]
];

$options = [
    "http" => [
        "method"  => "POST",
        "header"  => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
        "content" => json_encode($payload),
        "ignore_errors" => true
    ]
];

$context = stream_context_create($options);
$response = file_get_contents("https://message.ppurio.com/v1/send/alimtalk", false, $context);

// 실제 응답 반환
echo $response ?: json_encode(["error" => "전송 실패"]);
