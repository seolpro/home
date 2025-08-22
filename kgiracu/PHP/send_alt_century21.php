<?php
header("Content-Type: application/json; charset=utf-8");

/** ====== 환경설정 ====== */
$accountId     = 'cu03247';
$authKey       = '625b430045660862ab66697ef05b78965a46c546602b782dea0d24ad37a77777';
$templateCode  = 'ppur_2025082208155432533063175';  // [*1*]~[*6*] 사용
$senderProfile = '@cu03247';
$tokenFile     = __DIR__ . '/token.json';
$tokenExpireMinutes = 55; // 60분보다 약간 짧게

/** ====== 공통 유틸 ====== */
function getValidToken($accountId, $authKey, $tokenFile, $tokenExpireMinutes = 55) {
  // 캐시 사용
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

  if ($err) { http_response_code(500); echo json_encode(["error"=>"토큰 발급 오류","detail"=>$err]); exit; }

  $result = json_decode($response, true);
  if (!isset($result['token'])) { http_response_code(500); echo json_encode(["error"=>"토큰 응답 오류","response"=>$response]); exit; }

  file_put_contents($tokenFile, json_encode([
    'token'      => $result['token'],
    'created_at' => date("Y-m-d H:i:s")
  ]));

  return $result['token'];
}

function formatPhone($number) {
  $num = preg_replace('/[^0-9]/', '', $number);
  if ($num && $num[0] !== '0') $num = '0' . $num;
  return $num;
}

function formatKRW($v) {
  // "145000" -> "145,000원"
  if ($v === null || $v === '') return '';
  if (is_numeric($v)) return number_format((float)$v) . '원';
  // 숫자가 아닌 문자열이 들어오면 숫자만 추출 후 포맷
  $n = preg_replace('/[^0-9.]/', '', $v);
  if ($n === '') return $v;
  return number_format((float)$n) . '원';
}

/** ====== 입력 파라미터 ======
 *  GAS에서 보내는 JSON 구조 (예시):
 *  {
 *    "name":"홍길동",
 *    "to":"01012345678",
 *    "bookingId":"384219",
 *    "date":"2025-09-17",
 *    "time":"07:30",
 *    "course":"Out / IN",
 *    "fee":"145000",
 *    "note":"카트 희망"
 *  }
 */
$input = json_decode(file_get_contents("php://input"), true);
$name      = trim($input['name']      ?? '');
$to        = trim($input['to']        ?? '');
$bookingId = trim($input['bookingId'] ?? '');
$date      = trim($input['date']      ?? '');
$time      = trim($input['time']      ?? '');
$course    = trim($input['course']    ?? '');
$fee       = trim($input['fee']       ?? '');
$note      = trim($input['note']      ?? '');

if (!$name || !$to || !$bookingId || !$date || !$time || !$course) {
  http_response_code(400);
  echo json_encode(["error"=>"필수값 누락(name,to,bookingId,date,time,course)"]);
  exit;
}

// 수신자(신청자)
$userTarget = [[
  "to" => formatPhone($to),
  "name" => $name,
  // ※ 템플릿 변수: [*1*]~[*6*] (이름 제외, 6개만 사용)
  "changeWord" => [
    "var1" => $bookingId,           // [*1*] 예약번호
    "var2" => $date,                // [*2*] 이용일자
    "var3" => $time,                // [*3*] 이용시간
    "var4" => $course,              // [*4*] 예약 코스
    "var5" => formatKRW($fee),      // [*5*] 그린피
    "var6" => ($note !== '' ? $note : '-') // [*6*] 특이사항
  ]
]];

// 평일 08~22시엔 관리자도 동보
$now = new DateTime("now", new DateTimeZone("Asia/Seoul"));
$day = (int)$now->format('N');   // 1(월)~7(일)
$hour = (int)$now->format('G');  // 0~23
$isWorkTime = ($day >= 1 && $day <= 5 && $hour >= 8 && $hour < 22);

$staffTargets = [];
if ($isWorkTime) {
  $staffTargets = [
/*     [
      "to" => formatPhone("01063542163"),
      "name" => "[담당자] $name",
      "changeWord" => [
        "var1" => $bookingId,
        "var2" => $date,
        "var3" => $time,
        "var4" => $course,
        "var5" => formatKRW($fee),
        "var6" => ($note !== '' ? $note : '-')
      ]
    ], */
    [
      "to" => formatPhone("01071186639"),
      "name" => "[책임자] $name",
      "changeWord" => [
        "var1" => $bookingId,
        "var2" => $date,
        "var3" => $time,
        "var4" => $course,
        "var5" => formatKRW($fee),
        "var6" => ($note !== '' ? $note : '-')
      ]
    ]
  ];
}

$targets = array_merge($userTarget, $staffTargets);

// 요청 페이로드
$payload = [
  "account"       => $accountId,
  "messageType"   => "ALT",
  "senderProfile" => $senderProfile,
  "templateCode"  => $templateCode,
  "duplicateFlag" => "Y",
  "isResend"      => "N",
  "targetCount"   => count($targets),
  "targets"       => $targets,
  "refKey"        => "alt_" . time()
];

$token = getValidToken($accountId, $authKey, $tokenFile, $tokenExpireMinutes);

// 전송
$ch = curl_init("https://message.ppurio.com/v1/kakao");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Authorization: Bearer $token",
  "Content-Type: application/json; charset=utf-8"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
$response = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) { http_response_code(500); echo json_encode(["error"=>$err]); exit; }
http_response_code($httpCode);
echo $response;
