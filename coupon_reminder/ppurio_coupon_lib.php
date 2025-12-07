<?php
// /www/coupon_reminder/ppurio_coupon_lib.php
require_once __DIR__ . '/config.php';

/**
 * 쿠폰알림 전용 Ppurio 라이브러리
 *  - 토큰 발급: loan 코드와 동일한 방식
 *  - 발송 엔드포인트: https://message.ppurio.com/v1/kakao
 *  - messageType: ALT
 *  - templateCode: config.php 의 PPURIO_TEMPLATE_CODE 사용
 */

/* ============================================================
 * 1. 토큰 발급/로드
 *    (loan 코드의 getNewToken / loadToken 과 같은 구조지만
 *     이름만 coupon_ 으로 분리)
 * ========================================================== */

function coupon_getNewToken() {
    $account     = PPURIO_ACCOUNT;
    $authKey     = PPURIO_AUTH_KEY;
    $credentials = base64_encode("$account:$authKey");

    $ch = curl_init("https://message.ppurio.com/v1/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic $credentials",
        "Content-Type: application/json; charset=utf-8"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}'); // 빈 JSON 전송

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode === 200 && isset($result['token'])) {
        // 쿠폰 전용 토큰 파일에 저장
        file_put_contents(PPURIO_TOKEN_FILE, json_encode([
            'token' => $result['token']
        ], JSON_UNESCAPED_UNICODE));

        return $result['token'];
    }

    error_log('PPURIO coupon_getNewToken failed: ' . $response);
    return false;
}

function coupon_loadToken() {
    if (!file_exists(PPURIO_TOKEN_FILE)) {
        return coupon_getNewToken();
    }

    $tokenData = json_decode(file_get_contents(PPURIO_TOKEN_FILE), true);
    if (!isset($tokenData['token']) || !$tokenData['token']) {
        return coupon_getNewToken();
    }
    return $tokenData['token'];
}

/* ============================================================
 * 2. 유틸
 * ========================================================== */

function coupon_formatPhone($num) {
    $num = preg_replace('/[^0-9]/', '', $num);
    return (strlen($num) === 10) ? ('0' . $num) : $num;
}

function coupon_dbg_hex($s) { return strtoupper(bin2hex($s)); }

/* ============================================================
 * 3. 실제 발송 로우 함수 (loan 코드와 동일 구조)
 * ========================================================== */

function coupon_sendAlimtalkRaw(string $token, array $targets, ?string $templateCode = null) {
    if ($templateCode === null) {
        $templateCode = PPURIO_TEMPLATE_CODE;
    }

    $payload = [
        "account"       => PPURIO_ACCOUNT,
        "messageType"   => "ALT",
        "senderProfile" => PPURIO_SENDER_PROFILE,
        "templateCode"  => $templateCode,
        "duplicateFlag" => "Y",
        "isResend"      => "N",
        "targetCount"   => count($targets),
        "targets"       => $targets,
        "refKey"        => "coupon_" . time()
    ];

    $ch = curl_init("https://message.ppurio.com/v1/kakao");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json; charset=utf-8"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$httpCode, $response];
}

/* ============================================================
 * 4. 쿠폰용 래퍼 함수
 *    (기존 cron, 테스트 페이지에서 호출하는 함수)
 *    - 시그니처 유지: coupon_sendAlimtalk($to, $msg, $variables)
 *      ※ Alimtalk 실제 내용은 템플릿 + 변수로만 구성되며,
 *         $msg 는 디버깅용 텍스트 정도로만 사용(옵션)
 * ========================================================== */

/**
 * @param string $to        수신번호(010...)
 * @param string $msg       디버그용 메시지(템플릿에는 직접 안 들어갈 수 있음)
 * @param array  $variables 템플릿 변수값 [var1, var2, var3, var4 ...]
 * @param string|null $templateCode 필요 시 다른 템플릿 코드 사용
 * @return array ['ok'=>bool, 'httpCode'=>int, 'raw'=>string]
 */
function coupon_sendAlimtalk(string $to, string $msg, array $variables = [], ?string $templateCode = null) {
    // 디버그 로그
    error_log('COUPON_ALIMTALK send try | to=' . $to . ' | msg=' . $msg);

    $formattedTo = coupon_formatPhone($to);
    if (!$formattedTo || strlen($formattedTo) < 10) {
        return ['ok' => false, 'httpCode' => 0, 'raw' => 'INVALID_PHONE'];
    }

    // changeWord 구성: var1, var2, var3 ...
    $changeWord = [];
    foreach ($variables as $idx => $val) {
        $key = 'var' . ($idx + 1);    // [*1*] -> var1
        $changeWord[$key] = (string)$val;
    }

    $targets = [[
        "to"         => $formattedTo,
        "name"       => "고객님",   // 템플릿에 [*이름*] 쓸 경우 이 값 매핑
        "changeWord" => $changeWord
    ]];

    // 디버그 (필요 없으면 주석)
    error_log('COUPON_ACC: [' . PPURIO_ACCOUNT . '] hex=' . coupon_dbg_hex(PPURIO_ACCOUNT));
    error_log('COUPON_PRO: [' . PPURIO_SENDER_PROFILE . '] hex=' . coupon_dbg_hex(PPURIO_SENDER_PROFILE));
    error_log('COUPON_TPL: [' . ($templateCode ?? PPURIO_TEMPLATE_CODE) . '] hex=' . coupon_dbg_hex($templateCode ?? PPURIO_TEMPLATE_CODE));

    // 1차 전송
    $token = coupon_loadToken();
    if (!$token) {
        return ['ok' => false, 'httpCode' => 0, 'raw' => 'TOKEN_ISSUE_FAILED'];
    }

    list($httpCode, $response) = coupon_sendAlimtalkRaw($token, $targets, $templateCode);

    // 토큰 만료 등 문구 검사
    if (stripos($response, 'jwt expired') !== false ||
        stripos($response, 'Token issue failed') !== false) {

        $token = coupon_getNewToken();
        if ($token) {
            list($httpCode, $response) = coupon_sendAlimtalkRaw($token, $targets, $templateCode);
        }
    }

    // 응답 파싱
    $resArr = json_decode($response, true);
    $ok = false;

    if (is_array($resArr)) {
        if (isset($resArr['result']) && strtoupper($resArr['result']) === 'OK') {
            $ok = true;
        } elseif (isset($resArr['code']) && $resArr['code'] === '1000') {
            $ok = true;
        }
    }

    return [
        'ok'       => $ok,
        'httpCode' => $httpCode,
        'raw'      => $response
    ];
}
