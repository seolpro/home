<?php
// /www/coupon_reminder/config.php  (UTF-8, BOM 없음)

// 시간대
if (!defined('TIMEZONE')) {
    define('TIMEZONE', 'Asia/Seoul');
    date_default_timezone_set(TIMEZONE);
}

/**
 * DB 접속 정보 (기존 프로젝트와 동일하게 사용)
 * 필요시 DB_NAME, 비밀번호만 실제 값으로 바꿔주세요.
 */
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_NAME')) define('DB_NAME', 'seolhopro');
if (!defined('DB_USER')) define('DB_USER', 'seolhopro');
if (!defined('DB_PASS')) define('DB_PASS', 'ajou2130--');

// 쿠폰 이미지 업로드 경로
if (!defined('COUPON_UPLOAD_DIR')) {
    // /www/coupon_reminder/uploads
    define('COUPON_UPLOAD_DIR', __DIR__ . '/uploads');
}

// 뿌리오 알림톡 설정 (실제 값으로 변경)
if (!defined('PPURIO_ACCOUNT'))        define('PPURIO_ACCOUNT', 'aj9770');
if (!defined('PPURIO_AUTH_KEY'))       define('PPURIO_AUTH_KEY', '인증토큰뿌리오'); 
if (!defined('PPURIO_SENDER_PROFILE')) define('PPURIO_SENDER_PROFILE', '@타운카김설호');
if (!defined('PPURIO_TEMPLATE_CODE'))  define('PPURIO_TEMPLATE_CODE', 'ppur_*********');
if (!defined('PPURIO_TOKEN_FILE'))     define('PPURIO_TOKEN_FILE', __DIR__ . '/ppurio_coupon_token.json');

// 전화번호 숫자만 추출
if (!function_exists('only_digits')) {
    function only_digits(string $s): string {
        return preg_replace('/\D+/', '', $s);
    }
}
