<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');

$db = require dirname(__DIR__) . '/private_config/db_common.php';

define('APP_BASE_URL','https://seolhopro.mycafe24.com/tesla_port');
define('APP_CRON_KEY','f8K2mQ7xV4pN9cR6tW3yH5jL8sD1zA7uG4bX9eP2nM6kC3vT');


/* v1.2에서 사용하는 항만청 코드 */
define('PORT_CODE', '031');

/* 자동 조회기간 */
define('LOOKBACK_DAYS', 3);     // 오늘 기준 과거 3일
define('LOOKAHEAD_DAYS', 14);   // 오늘 기준 미래 14일

/* 화면 표시용 - 기존값 유지 가능 */
define('PORT_DISPLAY_NAME', '평택·당진항');


/* 공공데이터포털 - 해양수산부 선박운항정보 */
define('DATA_GO_KR_SERVICE_KEY','dd905fac2def3a0cd8fe61d575cf5ca50a858c77c2468d86897ce70816af4cce'); // 화면에 노출하지 말 것. Decoding 키 권장
define(
    'MOF_VESSEL_API_URL',
    'https://apis.data.go.kr/1192000/VsslEtrynd5/Info5'
);

// ==========================================
// 관세청 UNI-PASS
// ==========================================

// API001 : 화물통관진행정보조회
define('CUSTOMS_API_KEY', 'm280l216u150k036o010c020t8');

// API024 : 입출항보고내역조회
define('CUSTOMS_IOPR_API_KEY', 'i200q236y190l006d020p040f8');

//define('CUSTOMS_API_KEY', 'i200q236y190l006d020p040f8'); 

define(
    'CUSTOMS_API_URL',
    'https://unipass.customs.go.kr:38010/ext/rest/cargCsclPrgsInfoQry/retrieveCargCsclPrgsInfo'
);

/* 뿌리오 알림톡 */
define('PPURIO_ENABLED',true);
define('PPURIO_ACCOUNT','aj9770');
define('PPURIO_AUTH_KEY','08868d27d42a13b10954f7c9705063152e03d948b824bf336ff611be225957b9');
define('PPURIO_SENDER_PROFILE','@타운카김설호');
define('PPURIO_SENDER_PHONE','01071186639');
define('PPURIO_TEMPLATE_TESLA_PORT','ppur_2026100612023447407246868');

define('ADMIN_PASSWORD_HASH','$2y$12$t0tW6S02ImnlTsyhSbEEBeTnlxZ0oe/GFDMqnpnOsVrnAOppHZho2'); // password_hash('비밀번호', PASSWORD_DEFAULT)
return ['db'=>$db];

