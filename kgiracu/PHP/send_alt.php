<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$accountId     = 'cu03247';
$authKey       = '625b430045660862ab66697ef05b78965a46c546602b782dea0d24ad37a77777';
$templateCode = 'ppur_2025082210453220034558875'; // 실제 템플릿코드로 교체

function getNewToken() {
  global $account, $authKey;
  $credentials = base64_encode("$account:$authKey");

  $ch = curl_init("https://message.ppurio.com/v1/token");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      "Authorization: Basic $credentials",
      "Content-Type: application/json; charset=utf-8"
    ]
  ]);
  $res = curl_exec($ch);
  curl_close($ch);

  $result = json_decode($res, true);
  if (isset($result['token'])) {
    file_put_contents(__DIR__ . '/token.json', json_encode(['token' => $result['token']]));
    return $result['token'];
  }
  return false;
}

function loadToken() {
  $file = __DIR__ . '/token.json';
  if (!file_exists($file)) return getNewToken();
  $saved = json_decode(file_get_contents($file), true);
  return $saved['token'] ?? getNewToken();
}

function formatPhone($num) {
  return preg_replace('/[^0-9]/', '', $num);
}

$input = json_decode(file_get_contents("php://input"), true);
$name = $input['name'] ?? '';
$to   = $input['to'] ?? '';
$var1 = $input['var1'] ?? ''; // 제목
$var2 = $input['var2'] ?? ''; // 회신1
$var3 = $input['var3'] ?? ''; // 발신직원
$var4 = $input['var4'] ?? ''; // 회신2

if (!$name || !$to || !$var1 || !$var2 || !$var4) {
  echo json_encode(["code" => "9000", "description" => "❌ 필수 입력 누락"]);
  exit;
}
// 템플릿 변수에 매핑하는 과정임.
$targets = [[
  "to" => formatPhone($to),
  "name" => $name,
  "changeWord" => [
    "var1" => $var1, // 제목
    "var2" => $var2, // 회신내용1 변수 지정
    "var3" => $var4, // 발신직원 변수 지정
    "var4" => $var3  // 회신내용2 변수 지정
  ]
]];

function sendAlimtalk($token, $targets) {
  global $account, $templateCode;
  $payload = [
    "account" => $account,
    "messageType" => "ALT",
    "senderProfile" => "@cu03247",
    "templateCode" => $templateCode,
    "duplicateFlag" => "N",
    "isResend" => "N",
    "targetCount" => count($targets),
    "targets" => $targets,
    "refKey" => "notify_" . time()
  ];

  $ch = curl_init("https://message.ppurio.com/v1/kakao");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      "Authorization: Bearer $token",
      "Content-Type: application/json; charset=utf-8"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
  ]);
  $res = curl_exec($ch);
  curl_close($ch);
  return $res;
}

$token = loadToken();
$response = sendAlimtalk($token, $targets);

if (strpos($response, 'jwt expired') !== false || strpos($response, 'Token issue failed') !== false) {
  $token = getNewToken();
  $response = sendAlimtalk($token, $targets);
}

echo $response;
?>
