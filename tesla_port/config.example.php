<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');

$db = require dirname(__DIR__) . '/private_config/db_common.php';

define('APP_BASE_URL', 'https://YOURDOMAIN/tesla_port');
define('APP_CRON_KEY', 'KEEP_YOUR_EXISTING_SECRET');

define('DATA_GO_KR_SERVICE_KEY', 'KEEP_YOUR_EXISTING_KEY');
define('MOF_VESSEL_API_URL', 'https://apis.data.go.kr/1192000/VsslEtrynd5/Info5');
define('PORT_CODE', '031');
define('PORT_DISPLAY_NAME', '평택·당진항');
define('LOOKBACK_DAYS', 3);
define('LOOKAHEAD_DAYS', 14);

// 관세청 API001 : 화물통관진행정보조회 전용 인증키
define('CUSTOMS_API_KEY', 'KEEP_YOUR_API001_KEY');
define('CUSTOMS_API_URL', 'https://unipass.customs.go.kr:38010/ext/rest/cargCsclPrgsInfoQry/retrieveCargCsclPrgsInfo');

// 관세청 API024 : 입출항보고내역조회 전용 인증키
// API001 인증키와 서로 바꾸어 넣지 마세요.
define('CUSTOMS_IOPR_API_KEY', 'KEEP_YOUR_API024_KEY');
define('CUSTOMS_IOPR_API_URL', 'https://unipass.customs.go.kr:38010/ext/rest/ioprRprtQry/retrieveIoprRprtBrkd');

define('PPURIO_ENABLED', false);
define('PPURIO_ACCOUNT', '');
define('PPURIO_AUTH_KEY', '');
define('PPURIO_SENDER_PROFILE', '');
define('PPURIO_SENDER_PHONE', '');
define('PPURIO_TEMPLATE_TESLA_PORT', '');
define('ADMIN_PASSWORD_HASH', '');

// v1.9.2 MRN -> MSN 자동 화물탐색 안전범위
// 기본: 0001부터 최대 20번까지, 연속 5건 미응답이면 중단
if (!defined('CUSTOMS_MSN_SCAN_MAX')) define('CUSTOMS_MSN_SCAN_MAX', 20);
if (!defined('CUSTOMS_MSN_MISS_LIMIT')) define('CUSTOMS_MSN_MISS_LIMIT', 5);

// 반드시 모든 define() 이후 마지막에 return
return ['db' => $db];
