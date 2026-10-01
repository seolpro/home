<?php
declare(strict_types=1);

/**
 * /api/morning_brief.php
 *
 * 주식 아침 브리핑 자동 실행 API
 *
 * mode
 * ─────────────────────────────
 * send    : 정상 일일 브리핑 생성/저장/알림톡 발송
 * force   : 당일 실행 여부와 관계없이 강제 재실행
 * preview : 브리핑 생성 결과만 화면에 표시 (DB저장/발송 없음)
 *
 * 호출 예:
 * /api/morning_brief.php?mode=send&key=CRON_KEY
 */

require_once dirname(__DIR__) . '/lib.php';


/* =========================================================
 * 1. JSON 응답 기본 헤더
 * ========================================================= */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');


/* =========================================================
 * 2. CRON KEY 확인
 * ========================================================= */

$expectedKey = trim((string)cfg('security.cron_key'));
$inputKey    = trim((string)($_GET['key'] ?? ''));


/*
 * 서버 설정 자체에 cron_key가 없는 경우
 * 단순 "인증키 오류"와 구분하기 위해 별도 메시지를 반환합니다.
 */
if ($expectedKey === '') {
    json_out([
        'ok'      => false,
        'message' => '서버 CRON KEY가 설정되어 있지 않습니다.',
        'code'    => 'CRON_KEY_NOT_CONFIGURED',
    ], 500);
}


/*
 * URL에 ?key= 값이 없는 경우
 */
if ($inputKey === '') {
    json_out([
        'ok'      => false,
        'message' => '호출 URL에 CRON KEY가 없습니다.',
        'code'    => 'CRON_KEY_MISSING',
        'hint'    => 'URL 끝에 ?mode=send&key=CRON_KEY 형식으로 호출하세요.',
    ], 403);
}


/*
 * KEY 불일치
 */
if (!hash_equals($expectedKey, $inputKey)) {
    json_out([
        'ok'      => false,
        'message' => 'CRON KEY가 일치하지 않습니다.',
        'code'    => 'CRON_KEY_INVALID',
    ], 403);
}


/* =========================================================
 * 3. 실행 MODE 확인
 * ========================================================= */

$mode = strtolower(trim((string)($_GET['mode'] ?? 'send')));

$allowedModes = [
    'send',
    'force',
    'preview',
];

if (!in_array($mode, $allowedModes, true)) {
    json_out([
        'ok'      => false,
        'message' => '지원하지 않는 실행 모드입니다.',
        'code'    => 'INVALID_MODE',
        'mode'    => $mode,
        'allowed' => $allowedModes,
    ], 400);
}


/* =========================================================
 * 4. 브리핑 실행
 * ========================================================= */

try {

    /*
     * ---------------------------------------------
     * PREVIEW
     * ---------------------------------------------
     * 브리핑 내용만 생성하여 화면에서 확인합니다.
     *
     * DB 저장 X
     * 알림톡 발송 X
     */
    if ($mode === 'preview') {

        $brief = build_morning_brief();

        $message = (string)($brief['message'] ?? '');

        header('Content-Type: text/plain; charset=utf-8');

        echo "========================================\n";
        echo " 주식투자 아침 브리핑 PREVIEW\n";
        echo "========================================\n\n";

        if ($message === '') {
            echo "브리핑 내용이 생성되지 않았습니다.\n";
        } else {
            echo $message;
        }

        echo "\n\n";
        echo "========================================\n";
        echo "※ PREVIEW 모드입니다.\n";
        echo "※ DB 저장 및 알림톡 발송은 하지 않았습니다.\n";

        exit;
    }


    /*
     * ---------------------------------------------
     * SEND / FORCE
     * ---------------------------------------------
     */

    $force = ($mode === 'force');

    $result = run_daily_brief($force);


    /*
     * run_daily_brief() 반환값 보호
     */
    if (!is_array($result)) {
        throw new RuntimeException(
            'run_daily_brief()가 정상적인 결과 배열을 반환하지 않았습니다.'
        );
    }


    /*
     * 응답에 실행 모드 추가
     */
    $result['mode'] = $mode;


    /*
     * CRON KEY는 절대 응답에 포함하지 않습니다.
     */
    $httpStatus = !empty($result['ok']) ? 200 : 500;

    json_out($result, $httpStatus);


} catch (Throwable $e) {

    /*
     * 서버 내부 오류
     *
     * 운영환경에서는 파일경로/스택트레이스 등
     * 민감한 정보는 외부에 노출하지 않습니다.
     */
    json_out([
        'ok'      => false,
        'message' => $e->getMessage(),
        'code'    => 'BRIEF_EXECUTION_ERROR',
        'mode'    => $mode,
    ], 500);
}