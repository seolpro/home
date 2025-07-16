<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["error" => "Invalid request body"]);
    exit;
}

$name = $data["name"] ?? "";
$rawContact = $data["contact"] ?? "";
$attendees = $data["attendees"] ?? "";
$comment = $data["comment"] ?? "-";

// ✅ 연락처 정제 (숫자만 남김, 11자리 보정)
$contact = preg_replace('/[^0-9]/', '', $rawContact);
if (strlen($contact) === 10) $contact = '0' . $contact;
if (strlen($contact) !== 11) {
    echo json_encode(["error" => "Invalid contact format"]);
    exit;
}

// ✅ 토큰 발급 함수 (Basic 인증 방식, cURL 사용)
function getPpurioToken() {
    $accountId = "ajou9770";
    $authKey = "7bfe9eefc98c868431e0c3ca58c534ea37bbea9174a311c90be09636502ee296";
    $base64Auth = base64_encode("$accountId:$authKey");

    $ch = curl_init("https://message.ppurio.com/v1/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic $base64Auth",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return null;
    }
    curl_close($ch);

    $json = json_decode($response, true);
    return $json["token"] ?? null;
}

$token = getPpurioToken();
if (!$token) {
    echo json_encode(["error" => "토큰 발급 실패"]);
    exit;
}

// ✅ 알림톡 전송 요청 구성 (카카오톡 기본형 ALT)
$payload = [
    "account" => "ajou9770",
    "messageType" => "ALT", // 알림톡 기본형
    "senderProfile" => "@아주대의료원신협", // 실제 등록된 발신 프로필명
    "templateCode" => "ppur_2025071615063126394257542",
    "duplicateFlag" => "N",
    "targetCount" => 1,
    "refKey" => "test_" . time(),
    "isResend" => "N",
    "targets" => [[
        "to" => $contact,
        "name" => $name,
        "changeWord" => [
            "var1" => $contact,
            "var2" => $attendees,
            "var3" => $comment
        ]
    ]]
];

// ✅ 알림톡 전송 요청 (cURL)
$ch = curl_init("https://message.ppurio.com/v1/send/alimtalk");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errorMsg = curl_error($ch);
curl_close($ch);

// ✅ 응답 출력
if ($httpCode === 200) {
    echo $response;
} else {
    echo json_encode([
        "error" => "전송 실패",
        "httpCode" => $httpCode,
        "curlError" => $errorMsg
    ]);
}
