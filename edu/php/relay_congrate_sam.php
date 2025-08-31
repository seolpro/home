<?php
header("Content-Type: application/json; charset=utf-8");

// ✅ 계정 정보 설정
$accountId = 'ajou9770';
$authKey = '7bfe9eefc98c868431e0c3ca58c534ea37bbea9174a311c90be09636502ee296';
$templateCode = 'ppur_2025070411452223481585149';
$senderProfile = '@아주대의료원신협';
$tokenFile = __DIR__ . '/token.json';
$tokenExpireMinutes = 55; // 60분보다 약간 짧게 설정

// ✅ 1. 토큰 발급 또는 재사용
function getValidToken($accountId, $authKey, $tokenFile, $tokenExpireMinutes = 55) {
  if (file_exists($tokenFile)) {
    $data = json_decode(file_get_contents($tokenFile), true);
    $token = $data['token'] ?? '';
    $createdAt = strtotime($data['created_at'] ?? '');
    if ($token && $createdAt && (time() - $createdAt) < ($tokenExpireMinutes * 60)) {
      return $token;
    }
  }

  // 재발급
  $url = 'https://message.ppurio.com/v1/token';
  $credentials = base64_encode("$accountId:$authKey");

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic $credentials",
    "Content-Type: application/json"
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');

  $response = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);

  if ($err) {
    echo json_encode(["error" => "토큰 발급 오류", "detail" => $err]);
    exit;
  }

  $result = json_decode($response, true);
  if (!isset($result['token'])) {
    echo json_encode(["error" => "토큰 응답 오류", "response" => $response]);
    exit;
  }

  file_put_contents($tokenFile, json_encode([
    'token' => $result['token'],
    'created_at' => date("Y-m-d H:i:s")
  ]));

  return $result['token'];
}

// ✅ 2. 사용자 입력 수신
$input = json_decode(file_get_contents("php://input"), true);
$name = $input['name'] ?? '';
$to = $input['to'] ?? '';
$eventType = $input['eventType'] ?? '';
$eventDate = $input['eventDate'] ?? '';

if (!$name || !$to || !$eventType || !$eventDate) {
  echo json_encode(["error" => "필수값 누락"]);
  exit;
}

// ✅ 3. 연락처 정리
function formatPhone($number) {
  $num = preg_replace('/[^0-9]/', '', $number);
  if ($num && $num[0] !== '0') $num = '0' . $num;
  return $num;
}

// ✅ 4. 수신자 설정
$userTarget = [[
  "to" => formatPhone($to),
  "name" => $name,
  "changeWord" => [
    "var1" => $eventType,
    "var2" => $eventDate
  ]
]];

// ✅ 5. 요일·시간 확인 후 관리자 포함 여부 판단
$now = new DateTime("now", new DateTimeZone("Asia/Seoul"));
$day = (int)$now->format('N'); // 1~7
$hour = (int)$now->format('G'); // 0~23
$isWorkTime = $day >= 1 && $day <= 5 && $hour >= 8 && $hour < 22;

$staffTargets = [];
if ($isWorkTime) {
  $staffTargets = [
   /*  [
      "to" => formatPhone("01063542163"),
      "name" => "| TO 담당자 | ($name)",
      "changeWord" => [
        "var1" => $eventType,
        "var2" => $eventDate
      ]
    ], */
    [
      "to" => formatPhone("01071186639"),
      "name" => "| TO 책임자 | ($name)",
      "changeWord" => [
        "var1" => $eventType,
        "var2" => $eventDate
      ]
    ]
  ];
}

$allTargets = array_merge($userTarget, $staffTargets);

// ✅ 6. 알림톡 전송 요청
$token = getValidToken($accountId, $authKey, $tokenFile, $tokenExpireMinutes);

$payload = [
  "account" => $accountId,
  "messageType" => "ALT",
  "senderProfile" => $senderProfile,
  "templateCode" => $templateCode,
  "duplicateFlag" => "Y",
  "isResend" => "N",
  "targetCount" => count($allTargets),
  "targets" => $allTargets,
  "refKey" => "alt_" . time()
];

$ch = curl_init("https://message.ppurio.com/v1/kakao");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Authorization: Bearer $token",
  "Content-Type: application/json; charset=utf-8"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
  echo json_encode(["error" => $err]);
  exit;
}

echo $response;
